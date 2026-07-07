<?php

namespace Tests\Feature;

use App\Models\SubZone;
use App\Models\TenantApplication;
use App\Models\User;
use App\Models\Zone;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ZoneManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_zone_can_be_renamed(): void
    {
        [$host, $user] = $this->setupTenant('zone-update');

        $zone = Zone::on('tenant')->create(['name' => 'Mirpur', 'code' => 'ZN-01']);

        $this->actingAs($user)
            ->put("http://{$host}/dashboard/zones/{$zone->id}", ['name' => 'Mirpur Renamed', 'code' => 'ZN-01'])
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('zones', ['id' => $zone->id, 'name' => 'Mirpur Renamed'], 'tenant');
    }

    public function test_sub_zone_can_be_renamed_and_deleted(): void
    {
        [$host, $user] = $this->setupTenant('subzone-update');

        $zone = Zone::on('tenant')->create(['name' => 'Mirpur']);
        $subZone = SubZone::on('tenant')->create(['name' => 'Mirpur-1', 'zone_id' => $zone->id]);

        $this->actingAs($user)
            ->put("http://{$host}/dashboard/sub-zones/{$subZone->id}", [
                'name' => 'Mirpur-1-Renamed',
                'zone_id' => $zone->id,
            ])
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('sub_zones', ['id' => $subZone->id, 'name' => 'Mirpur-1-Renamed'], 'tenant');

        $this->actingAs($user)
            ->delete("http://{$host}/dashboard/sub-zones/{$subZone->id}")
            ->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseMissing('sub_zones', ['id' => $subZone->id], 'tenant');
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
}
