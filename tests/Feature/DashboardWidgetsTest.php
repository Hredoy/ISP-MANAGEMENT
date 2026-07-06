<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_index_returns_billing_widget_data_and_recent_payments(): void
    {
        [$host, $user, $router] = $this->setupTenant('dash-index');

        $client = $this->makeClient($router, ['status' => 'Active', 'expiry_date' => now()->addDays(30)->toDateString()]);
        $this->makeClient($router, ['pppoe_username' => 'suspended-'.uniqid(), 'status' => 'Suspended']);
        $this->makeClient($router, ['pppoe_username' => 'expired-'.uniqid(), 'status' => 'Active', 'expiry_date' => now()->subDay()->toDateString()]);

        $payment = Payment::on('tenant')->create([
            'client_id' => $client->id, 'amount' => 500, 'method' => 'sms_bkash',
            'status' => 'completed', 'paid_at' => now(),
        ]);
        PaymentTransaction::on('tenant')->create([
            'payment_id' => $payment->id, 'gateway' => 'bKash', 'transaction_id' => 'TRX-DASH-1',
            'amount' => 500, 'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get("http://{$host}/dashboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('billing.revenue_today', 500)
            ->where('billing.active_clients', 1)
            ->where('billing.suspended_clients', 1)
            ->where('billing.expired_clients', 1)
            ->has('recentPayments', 1)
        );
    }

    public function test_devices_status_widget_counts_online_and_offline_routers(): void
    {
        // setupTenant()'s provisioning flow already seeds one default router.
        [$host, $user] = $this->setupTenant('dash-devices');
        $totalRouters = Mikrotik::on('tenant')->count();

        $service = \Mockery::mock(MikroTikServiceInterface::class);
        $service->shouldReceive('testConnection')->times($totalRouters)
            ->andReturn(['ok' => true, 'message' => 'CONNECTION_OK', 'stats' => null]);
        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);
        $this->app->instance(MikroTikServiceFactory::class, $factory);

        $response = $this->actingAs($user)->getJson("http://{$host}/dashboard/widgets/devices-status");

        $response->assertOk()->assertJson(['total' => $totalRouters, 'online' => $totalRouters, 'offline' => 0]);
    }

    public function test_recent_payments_widget_returns_latest_payments(): void
    {
        [$host, $user, $router] = $this->setupTenant('dash-recent-payments');
        $client = $this->makeClient($router);

        $payment = Payment::on('tenant')->create([
            'client_id' => $client->id, 'amount' => 750, 'method' => 'sms_nagad',
            'status' => 'completed', 'paid_at' => now(),
        ]);
        PaymentTransaction::on('tenant')->create([
            'payment_id' => $payment->id, 'gateway' => 'Nagad', 'transaction_id' => 'TRX-DASH-2',
            'amount' => 750, 'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->getJson("http://{$host}/dashboard/widgets/recent-payments");

        $response->assertOk()->assertJsonCount(1, 'payments');
        $response->assertJsonPath('payments.0.client.full_name', $client->full_name);
    }

    /**
     * @return array{0: string, 1: User, 2: Mikrotik}
     */
    private function setupTenant(string $slug): array
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => ucfirst($slug),
            'slug' => $slug,
            'contact_name' => 'Admin',
            'email' => "admin@{$slug}.test",
            'phone' => '017'.substr(str_pad((string) crc32($slug), 8, '0'), 0, 8),
            'status' => 'pending',
        ]), 'secret-'.$slug);

        $this->tenantDatabase = $application->database_name;
        $this->reconnectTenant();

        $user = new User([
            'name' => 'Admin',
            'email' => "user-{$slug}@example.test",
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('tenant');
        $user->save();

        $router = Mikrotik::on('tenant')->create([
            'name' => 'Core Router', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret',
        ]);

        return ["{$slug}.localhost", $user, $router];
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
}
