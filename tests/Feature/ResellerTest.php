<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Mikrotik;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResellerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();
        parent::tearDown();
    }

    public function test_admin_can_list_resellers(): void
    {
        [$host, $user] = $this->setupTenant('reseller-list');

        Reseller::on('tenant')->create([
            'name' => 'Rahim Reseller', 'commission_rate' => 10, 'status' => 'active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->get("http://{$host}/dashboard/resellers")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reseller/Index')->has('resellers', 1));
    }

    public function test_admin_can_create_reseller(): void
    {
        [$host, $user] = $this->setupTenant('reseller-create');

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/resellers", [
                'name'            => 'Test Reseller',
                'commission_rate' => 15,
                'status'          => 'active',
            ])->assertRedirect();

        $this->reconnectTenant();

        $this->assertDatabaseHas('resellers', ['name' => 'Test Reseller', 'commission_rate' => 15], 'tenant');
    }

    public function test_admin_can_create_sub_reseller(): void
    {
        [$host, $user] = $this->setupTenant('reseller-sub');

        $parent = Reseller::on('tenant')->create([
            'name' => 'Parent Reseller', 'commission_rate' => 10, 'status' => 'active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/resellers", [
                'name'               => 'Sub Reseller',
                'commission_rate'    => 5,
                'status'             => 'active',
                'parent_reseller_id' => $parent->id,
            ])->assertRedirect();

        $this->reconnectTenant();

        $this->assertDatabaseHas('resellers', [
            'name'               => 'Sub Reseller',
            'parent_reseller_id' => $parent->id,
        ], 'tenant');
    }

    public function test_commission_is_auto_credited_on_completed_payment(): void
    {
        [$host, $user] = $this->setupTenant('reseller-commission');

        $reseller = Reseller::on('tenant')->create([
            'name' => 'Karim', 'commission_rate' => 10, 'status' => 'active',
        ]);

        $router = Mikrotik::on('tenant')->first();

        $client = Client::on('tenant')->create([
            'reseller_id'    => $reseller->id,
            'mikrotik_id'    => $router->id,
            'full_name'      => 'John Doe',
            'pppoe_username' => 'john123',
            'pppoe_password' => 'pass123',
            'package_name'   => 'Starter',
            'phone_number'   => '01700000000',
            'monthly_bill'   => 500,
            'full_address'   => 'Dhaka',
            'expiry_date'    => now()->addDays(30)->toDateString(),
            'status'         => 'Active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/billing/payments", [
                'client_id' => $client->id,
                'amount'    => 500,
                'method'    => 'cash',
                'status'    => 'completed',
                'paid_at'   => now()->toDateString(),
            ])->assertRedirect();

        $this->reconnectTenant();

        $this->assertDatabaseHas('reseller_commissions', [
            'reseller_id' => $reseller->id,
            'client_id'   => $client->id,
            'amount'      => 50.00,
            'status'      => 'pending',
        ], 'tenant');

        $reseller->refresh();
        $this->assertEquals('50.00', $reseller->wallet_balance);
    }

    public function test_commission_flows_up_multi_level_tree(): void
    {
        [$host, $user] = $this->setupTenant('reseller-multilevel');

        $parent = Reseller::on('tenant')->create([
            'name' => 'Master Reseller', 'commission_rate' => 5, 'status' => 'active',
        ]);

        $child = Reseller::on('tenant')->create([
            'name'               => 'Child Reseller',
            'commission_rate'    => 10,
            'parent_reseller_id' => $parent->id,
            'status'             => 'active',
        ]);

        $router = Mikrotik::on('tenant')->first();

        $client = Client::on('tenant')->create([
            'reseller_id'    => $child->id,
            'mikrotik_id'    => $router->id,
            'full_name'      => 'Jane Doe',
            'pppoe_username' => 'jane123',
            'pppoe_password' => 'pass456',
            'package_name'   => 'Pro',
            'phone_number'   => '01700000001',
            'monthly_bill'   => 1000,
            'full_address'   => 'Chittagong',
            'expiry_date'    => now()->addDays(30)->toDateString(),
            'status'         => 'Active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/billing/payments", [
                'client_id' => $client->id,
                'amount'    => 1000,
                'method'    => 'bkash',
                'status'    => 'completed',
                'paid_at'   => now()->toDateString(),
            ])->assertRedirect();

        $this->reconnectTenant();

        // Child gets 10% = ৳100
        $this->assertDatabaseHas('reseller_commissions', [
            'reseller_id' => $child->id,
            'amount'      => 100.00,
        ], 'tenant');

        // Parent gets 5% = ৳50
        $this->assertDatabaseHas('reseller_commissions', [
            'reseller_id' => $parent->id,
            'amount'      => 50.00,
        ], 'tenant');

        $child->refresh();
        $parent->refresh();
        $this->assertEquals('100.00', $child->wallet_balance);
        $this->assertEquals('50.00', $parent->wallet_balance);
    }

    public function test_no_commission_created_for_non_completed_payment(): void
    {
        [$host, $user] = $this->setupTenant('reseller-pending');

        $reseller = Reseller::on('tenant')->create([
            'name' => 'Test', 'commission_rate' => 10, 'status' => 'active',
        ]);

        $router = Mikrotik::on('tenant')->first();

        $client = Client::on('tenant')->create([
            'reseller_id'    => $reseller->id,
            'mikrotik_id'    => $router->id,
            'full_name'      => 'Pending Client',
            'pppoe_username' => 'pending123',
            'pppoe_password' => 'pass789',
            'package_name'   => 'Nano',
            'phone_number'   => '01700000002',
            'monthly_bill'   => 300,
            'full_address'   => 'Sylhet',
            'expiry_date'    => now()->addDays(30)->toDateString(),
            'status'         => 'Active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->post("http://{$host}/dashboard/billing/payments", [
                'client_id' => $client->id,
                'amount'    => 300,
                'method'    => 'bkash',
                'status'    => 'pending',
                'paid_at'   => now()->toDateString(),
            ])->assertRedirect();

        $this->reconnectTenant();

        $this->assertDatabaseCount('reseller_commissions', 0, 'tenant');
    }

    public function test_admin_can_view_reseller_detail(): void
    {
        [$host, $user] = $this->setupTenant('reseller-show');

        $reseller = Reseller::on('tenant')->create([
            'name' => 'Detail Reseller', 'commission_rate' => 8, 'status' => 'active',
        ]);

        $this->reconnectTenant();

        $this->actingAs($user)
            ->get("http://{$host}/dashboard/resellers/{$reseller->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Reseller/Show'));
    }

    // -------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: User}
     */
    private function setupTenant(string $slug): array
    {
        $this->dropTestTenantDatabases();

        $application = app(TenantProvisioningService::class)->approve(TenantApplication::create([
            'organization_name' => ucfirst($slug),
            'slug'              => $slug,
            'contact_name'      => 'Admin',
            'email'             => "admin@{$slug}.test",
            'phone'             => '017'.substr(str_pad((string) crc32($slug), 8, '0'), 0, 8),
            'status'            => 'pending',
        ]), 'secret-'.$slug);

        $this->tenantDatabase = $application->database_name;
        $this->reconnectTenant();

        $user = new User([
            'name'     => 'Admin',
            'email'    => "user-{$slug}@example.test",
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
}
