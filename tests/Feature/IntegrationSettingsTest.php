<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_sms_gateway_credentials_are_encrypted_at_rest(): void
    {
        [$host, $user] = $this->setupTenant('sms-create');

        $this->actingAs($user)->post("http://{$host}/dashboard/integrations/sms-gateways", [
            'provider' => 'twilio',
            'display_name' => 'Twilio Primary',
            'credentials' => [
                'account_sid' => 'AC123',
                'auth_token' => 'super-secret-token',
                'from_number' => '+15005550006',
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->reconnectTenant();
        $stored = SmsGateway::on('tenant')->where('display_name', 'Twilio Primary')->firstOrFail();

        $this->assertStringNotContainsString('super-secret-token', $stored->getAttributes()['credentials']);
        $this->assertSame('super-secret-token', $stored->credentials['auth_token']);
    }

    public function test_activating_a_gateway_deactivates_the_others(): void
    {
        [$host, $user] = $this->setupTenant('sms-activate');

        $gatewayA = SmsGateway::on('tenant')->create([
            'provider' => 'ssl_wireless', 'display_name' => 'A', 'is_active' => true,
        ]);
        $gatewayB = SmsGateway::on('tenant')->create([
            'provider' => 'alpha_sms', 'display_name' => 'B', 'is_active' => false,
        ]);

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/integrations/sms-gateways/{$gatewayB->id}/activate")
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('sms_gateways', ['id' => $gatewayB->id, 'is_active' => 1], 'tenant');
        $this->assertDatabaseHas('sms_gateways', ['id' => $gatewayA->id, 'is_active' => 0], 'tenant');
    }

    public function test_test_send_calls_the_gateway_driver(): void
    {
        [$host, $user] = $this->setupTenant('sms-test-send');

        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 200)]);

        $gateway = SmsGateway::on('tenant')->create([
            'provider' => 'twilio',
            'display_name' => 'Twilio Primary',
            'is_active' => true,
            'credentials' => ['account_sid' => 'AC123', 'auth_token' => 'tok', 'from_number' => '+15005550006'],
        ]);

        $this->actingAs($user)
            ->postJson("http://{$host}/dashboard/integrations/sms-gateways/{$gateway->id}/test", ['phone' => '+15005550001'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));
    }

    /**
     * @return array{0: string, 1: User}
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

        return ["{$slug}.localhost", $user];
    }

    private function reconnectTenant(): void
    {
        Config::set('database.connections.tenant', array_replace(Config::get('database.connections.mysql'), [
            'database' => $this->tenantDatabase,
        ]));
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function dropTestTenantDatabases(): void
    {
        foreach (DB::select("SHOW DATABASES LIKE 'test\\_tenant\\_%'") as $row) {
            $database = array_values((array) $row)[0];

            DB::statement('DROP DATABASE IF EXISTS `'.str_replace('`', '``', $database).'`');
        }
    }
}
