<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\SmsDeviceToken;
use App\Models\TenantApplication;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentSmsMatchTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_rejects_request_without_a_valid_device_token(): void
    {
        [$host] = $this->setupTenant('sms-match-no-token');

        $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX1',
        ])->assertStatus(401);
    }

    public function test_matches_client_extends_expiry_and_unsuspends_router(): void
    {
        [$host, $router, $token] = $this->setupTenant('sms-match-ok');
        $client = $this->makeClient($router, ['status' => 'Suspended', 'expiry_date' => now()->subDays(3)->toDateString()]);

        $service = $this->fakeMikroTikService();
        $service->shouldReceive('enableUser')->once()->with($client->pppoe_username)->andReturn(true);

        $this->fakeSmsService(['ok' => true, 'message' => 'SENT']);

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-MATCH-1',
            'raw_sender' => 'bKash',
            'raw_body' => 'You have received Tk 500.00 from 01799999999',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'completed']);

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertSame('Active', $updated->status);
        $this->assertSame(now()->addDays(30)->toDateString(), $updated->expiry_date);
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_id' => 'TRX-MATCH-1',
            'status' => 'completed',
        ], 'tenant');
        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'status' => 'completed',
        ], 'tenant');
    }

    public function test_duplicate_transaction_id_is_rejected_idempotently(): void
    {
        [$host, $router, $token] = $this->setupTenant('sms-match-dup');
        $client = $this->makeClient($router);

        $payment = Payment::on('tenant')->create([
            'client_id' => $client->id,
            'amount' => 500,
            'method' => 'sms_bkash',
            'status' => 'completed',
        ]);
        PaymentTransaction::on('tenant')->create([
            'payment_id' => $payment->id,
            'gateway' => 'bKash',
            'transaction_id' => 'TRX-DUP',
            'amount' => 500,
            'status' => 'completed',
        ]);

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-DUP',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'duplicate']);
    }

    public function test_unknown_phone_is_held_for_manual_review(): void
    {
        [$host, , $token] = $this->setupTenant('sms-match-unknown');

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01700000000',
            'transaction_id' => 'TRX-UNKNOWN',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'unmatched']);

        $this->reconnectTenant();
        $this->assertDatabaseHas('payment_transactions', ['transaction_id' => 'TRX-UNKNOWN', 'status' => 'unmatched'], 'tenant');
        $this->assertDatabaseHas('payments', ['status' => 'unmatched', 'client_id' => null], 'tenant');
    }

    public function test_multiple_clients_sharing_a_phone_are_flagged(): void
    {
        [$host, $router, $token] = $this->setupTenant('sms-match-multi');
        $this->makeClient($router, ['pppoe_username' => 'client-a-'.uniqid()]);
        $this->makeClient($router, ['pppoe_username' => 'client-b-'.uniqid()]);

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-MULTI',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'multiple_clients']);
    }

    public function test_partial_payment_is_flagged_and_does_not_extend_or_unsuspend(): void
    {
        [$host, $router, $token] = $this->setupTenant('sms-match-partial');
        $client = $this->makeClient($router, ['status' => 'Suspended']);

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 200,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-PARTIAL',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'partial']);

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertSame('Suspended', $updated->status);
    }

    public function test_router_unreachable_still_records_payment_but_flags_it(): void
    {
        [$host, $router, $token] = $this->setupTenant('sms-match-router-down');
        $client = $this->makeClient($router, ['status' => 'Suspended']);

        $this->fakeMikroTikService()
            ->shouldReceive('enableUser')
            ->once()
            ->andThrow(new \RuntimeException('MikroTik router is unreachable.'));

        $this->fakeSmsService(['ok' => true, 'message' => 'SENT']);

        $response = $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-ROUTER-DOWN',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk()->assertJson(['status' => 'completed']);

        $this->reconnectTenant();
        $updated = Client::on('tenant')->findOrFail($client->id);
        $this->assertSame('Active', $updated->status);
        $payment = Payment::on('tenant')->where('client_id', $client->id)->firstOrFail();
        $this->assertStringContainsString('ROUTER_UNREACHABLE', $payment->meta['router_warning'] ?? '');
    }

    /**
     * @return array{0: string, 1: Mikrotik, 2: string}
     */
    private function setupTenant(string $slug): array
    {
        $this->dropTestTenantDatabases();

        $application = app(\App\Services\TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => ucfirst($slug),
            'slug' => $slug,
            'contact_name' => 'Admin',
            'email' => "admin@{$slug}.test",
            'phone' => '017'.substr(str_pad((string) crc32($slug), 8, '0'), 0, 8),
            'status' => 'pending',
        ]), 'secret-'.$slug);

        $this->tenantDatabase = $application->database_name;
        $this->reconnectTenant();

        $router = Mikrotik::on('tenant')->create([
            'name' => 'Core Router', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret',
        ]);

        [, $plainToken] = SmsDeviceToken::generate('Test device', 'tenant');

        return ["{$slug}.localhost", $router, $plainToken];
    }

    private function makeClient(Mikrotik $router, array $overrides = []): Client
    {
        return Client::on('tenant')->create(array_merge([
            'mikrotik_id' => $router->id,
            'pppoe_username' => 'client-'.uniqid(),
            'pppoe_password' => 'secret123',
            'package_name' => 'Nano',
            'full_name' => 'Test Client',
            'phone_number' => '01799999999',
            'monthly_bill' => 500,
            'full_address' => 'Somewhere',
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'Active',
        ], $overrides));
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function fakeMikroTikService(): MockInterface
    {
        $service = \Mockery::mock(MikroTikServiceInterface::class);

        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);

        $this->app->instance(MikroTikServiceFactory::class, $factory);

        return $service;
    }

    /**
     * @param  array{ok: bool, message: string}  $result
     */
    private function fakeSmsService(array $result): void
    {
        $this->mock(SmsService::class, function (MockInterface $mock) use ($result) {
            $mock->shouldReceive('sendPaymentConfirmation')->andReturn($result);
        });
    }
}
