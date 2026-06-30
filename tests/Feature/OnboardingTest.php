<?php

namespace Tests\Feature;

use App\Models\TenantApplication;
use App\Services\SmsService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboard_creates_application_and_returns_progress_payload(): void
    {
        Storage::fake('public');

        $this->app->bind(TenantProvisioningService::class, fn () => new class extends TenantProvisioningService
        {
            public function approve(TenantApplication $application, ?string $adminPassword = null): TenantApplication
            {
                $application->update([
                    'status' => 'approved',
                    'database_name' => 'production_alpha_net',
                    'subdomain' => 'alpha-net.yourplatform.com',
                    'admin_email' => $application->email,
                    'approved_at' => now(),
                ]);

                return $application->fresh();
            }
        });

        $this->mock(SmsService::class)
            ->shouldReceive('sendAdminCredentials')
            ->once();

        $response = $this->post('/api/onboard', [
            'company_name' => 'Alpha Net',
            'district' => 'Dhaka',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'contact_name' => 'Admin User',
            'contact_email' => 'admin@example.com',
            'contact_phone' => '01700000000',
            'plan' => 'starter',
            'mikrotik_ip' => '192.168.88.1',
            'olt_ip' => '10.10.10.2',
            'olt_brand' => 'Huawei',
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('tenant.subdomain', 'alpha-net.yourplatform.com')
            ->assertJsonPath('tenant.database_name', 'production_alpha_net')
            ->assertJsonPath('steps.3.status', 'complete');

        $this->assertDatabaseHas('tenant_applications', [
            'organization_name' => 'Alpha Net',
            'district' => 'Dhaka',
            'plan' => 'starter',
            'mikrotik_ip' => '192.168.88.1',
            'olt_brand' => 'Huawei',
            'status' => 'approved',
        ]);
    }
}
