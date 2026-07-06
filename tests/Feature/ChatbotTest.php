<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\Tenant;
use App\Models\TenantApplication;
use App\Models\User;
use App\Services\Chat\ChatbotService;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabase;

    protected function tearDown(): void
    {
        $this->dropTestTenantDatabases();

        parent::tearDown();
    }

    public function test_local_kb_answers_a_known_keyword_instantly_without_any_api_call(): void
    {
        [$host, $user] = $this->setupTenant('chatbot-kb');
        Http::fake();

        $response = $this->actingAs($user)->postJson("http://{$host}/dashboard/chatbot/ask", [
            'message' => 'My internet is very slow today',
        ]);

        $response->assertOk()->assertJson(['tier' => 'local_kb']);
        Http::assertNothingSent();
    }

    public function test_falls_through_to_groq_when_no_kb_match(): void
    {
        [$host, $user] = $this->setupTenant('chatbot-groq');
        Config::set('services.groq.key', 'test-groq-key');

        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => 'You can reach our office at 9am-6pm.']],
            ]], 200),
        ]);

        $response = $this->actingAs($user)->postJson("http://{$host}/dashboard/chatbot/ask", [
            'message' => 'What are your office hours?',
        ]);

        $response->assertOk()->assertJson(['tier' => 'groq', 'answer' => 'You can reach our office at 9am-6pm.']);
        Http::assertSentCount(1);
    }

    /**
     * Exercises ChatbotService directly, within a single tenancy()->initialize()/end() cycle,
     * rather than through two separate HTTP requests: each HTTP request re-runs
     * SetTenantDatabase's initialize()/end(), and CacheTenancyBootstrapper rebuilds the cache
     * repository on every initialize() call, so two separate requests in a test don't share the
     * same array-store cache the way two requests against real, persistent Redis would (see
     * TenantCacheTest's docblock for the same testing-harness limitation).
     */
    public function test_a_successful_groq_answer_is_cached_permanently_and_not_requested_twice(): void
    {
        [, , $tenant] = $this->setupTenant('chatbot-groq-cache');
        Config::set('services.groq.key', 'test-groq-key');

        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => 'You can reach our office at 9am-6pm.']],
            ]], 200),
        ]);

        tenancy()->initialize($tenant);
        $service = app(ChatbotService::class);

        $first = $service->answer('What are your office hours?');
        $second = $service->answer('What are your office hours?');

        tenancy()->end();

        $this->assertSame('groq', $first['tier']);
        $this->assertSame('groq', $second['tier']);
        Http::assertSentCount(1);
    }

    public function test_falls_back_to_gemini_when_groq_fails(): void
    {
        [$host, $user] = $this->setupTenant('chatbot-gemini');
        Config::set('services.groq.key', 'test-groq-key');
        Config::set('services.gemini.key', 'test-gemini-key');

        Http::fake([
            'api.groq.com/*' => Http::response([], 500),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Gemini says hello.']]]]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("http://{$host}/dashboard/chatbot/ask", [
            'message' => 'Do you support fiber connections?',
        ]);

        $response->assertOk()->assertJson(['tier' => 'gemini', 'answer' => 'Gemini says hello.']);
    }

    public function test_returns_a_graceful_fallback_when_no_provider_is_configured(): void
    {
        [$host, $user] = $this->setupTenant('chatbot-none');
        Config::set('services.groq.key', null);
        Config::set('services.gemini.key', null);
        Http::fake();

        $response = $this->actingAs($user)->postJson("http://{$host}/dashboard/chatbot/ask", [
            'message' => 'Do you support fiber connections?',
        ]);

        $response->assertOk()->assertJson(['tier' => 'fallback']);
        Http::assertNothingSent();
    }

    public function test_kb_find_match_is_case_insensitive_and_checks_all_keywords(): void
    {
        [, , $tenant] = $this->setupTenant('chatbot-kb-direct');

        tenancy()->initialize($tenant);
        $match = KbArticle::findMatch('Amar BILL koto?');
        tenancy()->end();

        $this->assertNotNull($match);
        $this->assertSame('Bill / payment question', $match->question);
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
