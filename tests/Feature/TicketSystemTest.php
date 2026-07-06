<?php

namespace Tests\Feature;

use App\Events\DeviceStatusChanged;
use App\Events\NewTicketCreated;
use App\Events\PaymentReceivedBroadcast;
use App\Models\Mikrotik;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MikroTik\Contracts\MikroTikServiceInterface;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\TenantProvisioningService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class TicketSystemTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_creating_a_ticket_categorizes_sets_sla_and_broadcasts(): void
    {
        [, , $tenant] = $this->setupTenant('ticket-create');
        Event::fake([NewTicketCreated::class]);

        tenancy()->initialize($tenant);
        $ticket = app(TicketService::class)->create('My internet is very slow since morning', null, 'urgent');
        // Cast attributes (like sla_due_at's 'datetime' cast) resolve the model's connection to
        // format the date, so read everything needed while tenancy is still active - accessing
        // them after tenancy()->end() throws "Database connection [tenant] not configured"
        // once the tenant connection config has been torn down.
        $category = $ticket->category;
        $priority = $ticket->priority;
        $status = $ticket->status;
        $slaDueAt = $ticket->sla_due_at;
        $ticketId = $ticket->id;
        tenancy()->end();

        $this->assertSame('Speed', $category);
        $this->assertSame('urgent', $priority);
        $this->assertSame('open', $status);
        $this->assertNotNull($slaDueAt);
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $slaDueAt->timestamp, 5);

        Event::assertDispatched(NewTicketCreated::class, fn ($event) => $event->ticket->id === $ticketId);
    }

    public function test_new_ticket_is_assigned_to_the_least_loaded_technician(): void
    {
        [, , $tenant] = $this->setupTenant('ticket-assign');

        tenancy()->initialize($tenant);

        Role::findOrCreate('Technician', 'web');
        $busy = User::on('tenant')->create(['name' => 'Busy Tech', 'email' => 'busy@example.test', 'password' => Hash::make('password')]);
        $busy->assignRole('Technician');
        $free = User::on('tenant')->create(['name' => 'Free Tech', 'email' => 'free@example.test', 'password' => Hash::make('password')]);
        $free->assignRole('Technician');

        Ticket::create(['assigned_to' => $busy->id, 'subject' => 'Existing', 'category' => 'Other', 'priority' => 'normal', 'status' => 'open', 'message' => 'x']);

        $ticket = app(TicketService::class)->create('Bill amount is wrong', null, 'normal');

        tenancy()->end();

        $this->assertSame($free->id, $ticket->assigned_to);
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame('Billing', $ticket->category);
    }

    public function test_escalate_overdue_flips_status_and_leaves_others_alone(): void
    {
        [, , $tenant] = $this->setupTenant('ticket-escalate');

        tenancy()->initialize($tenant);
        $overdue = Ticket::create(['subject' => 'Overdue', 'category' => 'Other', 'priority' => 'urgent', 'status' => 'open', 'message' => 'x', 'sla_due_at' => now()->subHour()]);
        $onTime = Ticket::create(['subject' => 'On time', 'category' => 'Other', 'priority' => 'normal', 'status' => 'open', 'message' => 'y', 'sla_due_at' => now()->addHours(20)]);
        $alreadyResolved = Ticket::create(['subject' => 'Resolved', 'category' => 'Other', 'priority' => 'urgent', 'status' => 'resolved', 'message' => 'z', 'sla_due_at' => now()->subDay()]);

        $count = app(TicketService::class)->escalateOverdue();
        $overdue->refresh();
        $onTime->refresh();
        $alreadyResolved->refresh();
        tenancy()->end();

        $this->assertSame(1, $count);
        $this->assertSame('escalated', $overdue->status);
        $this->assertSame('open', $onTime->status);
        $this->assertSame('resolved', $alreadyResolved->status);
    }

    public function test_ticket_can_be_created_via_the_dashboard_endpoint(): void
    {
        [$host, $user] = $this->setupTenant('ticket-http');

        $this->actingAs($user)->post("http://{$host}/dashboard/tickets", [
            'message' => 'Net nai since last night',
            'priority' => 'urgent',
        ])->assertRedirect();

        $this->reconnectTenant();
        $this->assertDatabaseHas('tickets', ['subject' => 'Net nai since last night', 'category' => 'Connectivity'], 'tenant');
    }

    public function test_completed_payment_broadcasts_payment_received_event(): void
    {
        [$host, , $tenant] = $this->setupTenant('ticket-payment-broadcast');
        Event::fake([PaymentReceivedBroadcast::class]);

        tenancy()->initialize($tenant);
        $router = Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret']);
        \App\Models\Client::on('tenant')->create([
            'mikrotik_id' => $router->id, 'pppoe_username' => 'client-1', 'pppoe_password' => 'secret123',
            'package_name' => 'Nano', 'full_name' => 'Test Client', 'phone_number' => '01799999999',
            'monthly_bill' => 500, 'full_address' => 'Addr', 'expiry_date' => now()->addDays(30)->toDateString(), 'status' => 'Active',
        ]);
        [, $plainToken] = \App\Models\SmsDeviceToken::generate('Test device', 'tenant');
        tenancy()->end();

        $service = \Mockery::mock(MikroTikServiceInterface::class);
        $service->shouldReceive('enableUser')->andReturn(true);
        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);
        $this->app->instance(MikroTikServiceFactory::class, $factory);
        $this->mock(\App\Services\SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendPaymentConfirmation')->andReturn(['ok' => true, 'message' => 'SENT']);
        });

        $this->postJson("http://{$host}/api/payments/sms-match", [
            'provider' => 'bKash',
            'amount' => 500,
            'sender_phone' => '01799999999',
            'transaction_id' => 'TRX-BROADCAST-1',
        ], ['Authorization' => "Bearer {$plainToken}"])->assertOk()->assertJson(['status' => 'completed']);

        Event::assertDispatched(PaymentReceivedBroadcast::class);
    }

    public function test_device_status_change_broadcasts_only_on_transition(): void
    {
        [$host, $user, $tenant] = $this->setupTenant('ticket-device-broadcast');

        tenancy()->initialize($tenant);
        $router = Mikrotik::on('tenant')->create(['name' => 'R', 'host' => '10.0.0.1', 'port' => 8728, 'username' => 'admin', 'password' => 'secret', 'status' => 'offline']);
        tenancy()->end();

        Event::fake([DeviceStatusChanged::class]);

        $service = \Mockery::mock(MikroTikServiceInterface::class);
        $service->shouldReceive('getSystemResources')->twice()->andReturn(['uptime' => '1d']);
        $factory = \Mockery::mock(MikroTikServiceFactory::class);
        $factory->shouldReceive('make')->andReturn($service);
        $this->app->instance(MikroTikServiceFactory::class, $factory);

        // offline -> online: a real transition, should broadcast.
        $this->actingAs($user)->postJson("http://{$host}/dashboard/mikrotik/{$router->id}/check-connection")->assertOk();

        // online -> online: no transition, no additional broadcast.
        $this->actingAs($user)->postJson("http://{$host}/dashboard/mikrotik/{$router->id}/check-connection")->assertOk();

        Event::assertDispatchedTimes(DeviceStatusChanged::class, 1);
    }

    /**
     * @return array{0: string, 1: User, 2: Tenant}
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

        return ["{$slug}.localhost", $user, $application->tenant];
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
