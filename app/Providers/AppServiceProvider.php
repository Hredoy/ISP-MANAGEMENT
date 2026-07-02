<?php

namespace App\Providers;

use App\Listeners\LogMikrotikActivity;
use App\Services\MikroTik\MikroTikServiceFactory;
use App\Services\MikroTik\ModeResolver;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModeResolver::class);
        $this->app->singleton(MikroTikServiceFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->validateEnvironment();

        Event::subscribe(LogMikrotikActivity::class);

        Vite::prefetch(concurrency: 3);
    }

    private function validateEnvironment(): void
    {
        if (! config('required_env.enabled')) {
            return;
        }

        $keys = config('required_env.keys', []);

        if (App::environment('production')) {
            $keys = [...$keys, ...config('required_env.production_keys', [])];
        }

        $missing = collect($keys)
            ->filter(fn (string $key) => blank(env($key)))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Missing required environment keys: '.$missing->implode(', '));
        }
    }
}
