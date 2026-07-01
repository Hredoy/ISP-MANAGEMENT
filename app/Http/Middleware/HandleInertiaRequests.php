<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $enabledModules = [];
        $tenantStatus = null;

        if (tenancy()->initialized && tenant()) {
            $tenantStatus = tenant()->status;
            $enabledModules = tenant()->modules()
                ->where('enabled', true)
                ->whereHas('module', fn ($query) => $query->where('is_active', true))
                ->with('module:id,slug,name')
                ->get()
                ->pluck('module.slug')
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => [
                'status' => $tenantStatus,
                'enabledModules' => $enabledModules,
            ],
        ];
    }
}
