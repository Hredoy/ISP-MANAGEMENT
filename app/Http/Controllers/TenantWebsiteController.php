<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Setting;
use App\Models\TenantBlog;
use App\Models\TenantComplaint;
use App\Models\TenantConnectionRequest;
use App\Models\TenantFrontendSection;
use App\Models\TenantFrontendSetting;
use App\Models\TenantFrontendSlider;
use App\Models\TenantManualPayment;
use App\Models\TenantReferral;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantWebsiteController extends Controller
{
    public function home(Request $request): Response
    {
        $this->assertTenantWebsite();
        $this->ensureDefaults();

        return Inertia::render('Tenant/Website', $this->websiteProps());
    }

    public function packages(Request $request): Response
    {
        return $this->home($request);
    }

    public function newConnection(Request $request): Response
    {
        $this->assertTenantWebsite();
        $this->ensureDefaults();

        return Inertia::render('Tenant/Website', [
            ...$this->websiteProps(),
            'openModal' => 'connection',
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->home($request);
    }

    public function blogs(): Response
    {
        $this->assertTenantWebsite();
        $this->ensureDefaults();

        return Inertia::render('Tenant/Website', [
            ...$this->websiteProps(),
            'openSection' => 'blogs',
        ]);
    }

    public function blog(string $slug): Response
    {
        $this->assertTenantWebsite();
        $this->ensureDefaults();
        $blog = TenantBlog::on('tenant')->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return Inertia::render('Tenant/Website', [
            ...$this->websiteProps(),
            'blogDetail' => $blog,
        ]);
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        $this->assertTenantWebsite();

        $validated = $request->validate([
            'website' => ['nullable', 'prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'area' => ['nullable', 'string', 'max:255'],
            'package' => ['nullable', 'string', 'max:255'],
            'preferred_connection_date' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        TenantConnectionRequest::on('tenant')->create($this->withVisitor($request, $validated));

        return back()->with('success', 'Connection request submitted successfully.');
    }

    public function storeComplaint(Request $request): RedirectResponse
    {
        $this->assertTenantWebsite();

        $max = (int) data_get($this->frontendSettings(), 'complaint_message_max', 255);
        $validated = $request->validate([
            'website' => ['nullable', 'prohibited'],
            'customer_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:50'],
            'complaint_type' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:'.$max],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('frontend/complaints', 'public');
        }

        unset($validated['image']);
        TenantComplaint::on('tenant')->create($this->withVisitor($request, $validated));

        return back()->with('success', 'Complaint submitted successfully.');
    }

    public function storeReferral(Request $request): RedirectResponse
    {
        $this->assertTenantWebsite();

        $validated = $request->validate([
            'website' => ['nullable', 'prohibited'],
            'friend_name' => ['required', 'string', 'max:255'],
            'friend_phone' => ['required', 'string', 'max:50'],
            'referrer_user_id' => ['nullable', 'string', 'max:100'],
        ]);

        TenantReferral::on('tenant')->create($this->withVisitor($request, $validated));

        return back()->with('success', 'Referral submitted successfully.');
    }

    public function storeManualPayment(Request $request): RedirectResponse
    {
        $this->assertTenantWebsite();

        $validated = $request->validate([
            'website' => ['nullable', 'prohibited'],
            'customer_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'max:100'],
            'screenshot' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('screenshot')) {
            $validated['screenshot_path'] = $request->file('screenshot')->store('frontend/payments', 'public');
        }

        unset($validated['screenshot']);
        TenantManualPayment::on('tenant')->create($this->withVisitor($request, $validated));

        return back()->with('success', 'Manual payment submitted successfully.');
    }

    private function websiteProps(): array
    {
        $organization = Setting::on('tenant')->where('key', 'organization')->value('value') ?? [];
        $settings = $this->frontendSettings();

        return [
            'tenant' => [
                'id' => tenant()->getTenantKey(),
                'name' => data_get($settings, 'site_name') ?: ($organization['name'] ?? tenant('organization_name') ?? 'Cloud Fiber ISP'),
                'logo' => data_get($settings, 'logo'),
                'favicon' => data_get($settings, 'favicon'),
                'phone' => data_get($settings, 'phone') ?: ($organization['phone'] ?? '09611-000-000'),
                'email' => data_get($settings, 'email') ?: ($organization['email'] ?? 'support@example.com'),
                'address' => data_get($settings, 'address') ?: ($organization['address'] ?? 'Service center address'),
                'business_type' => $organization['business_type'] ?? 'Broadband Internet Service Provider',
                'district' => $organization['district'] ?? null,
                'about' => data_get($settings, 'about'),
            ],
            'frontend' => [
                'primary_color' => data_get($settings, 'primary_color', '#0aa4e8'),
                'accent_color' => data_get($settings, 'accent_color', '#8cc63f'),
                'seo_title' => data_get($settings, 'seo_title'),
                'seo_description' => data_get($settings, 'seo_description'),
                'seo_keywords' => data_get($settings, 'seo_keywords'),
                'og_image' => data_get($settings, 'og_image'),
                'referral_offer' => data_get($settings, 'referral_offer', 'Refer a friend and get special rewards.'),
                'payment_methods' => data_get($settings, 'payment_methods', ['bKash', 'Nagad', 'Rocket', 'Bank']),
                'app_qr_image' => data_get($settings, 'app_qr_image'),
                'payment_image' => data_get($settings, 'payment_image'),
                'footer_about' => data_get($settings, 'footer_about'),
                'terms' => data_get($settings, 'terms'),
                'privacy' => data_get($settings, 'privacy'),
                'refund' => data_get($settings, 'refund'),
                'menu' => data_get($settings, 'menu', []),
            ],
            'sliders' => TenantFrontendSlider::on('tenant')->where('is_active', true)->orderBy('sort_order')->get(),
            'sections' => TenantFrontendSection::on('tenant')->where('is_enabled', true)->get()->keyBy('key'),
            'blogs' => TenantBlog::on('tenant')->where('is_published', true)->latest('published_at')->limit(3)->get(),
            'packages' => Package::on('tenant')->where('is_public', true)->orderBy('price')->get(['name', 'rate_limit', 'price', 'description'])->values(),
            'zones' => Zone::on('tenant')->orderBy('name')->pluck('name'),
        ];
    }

    private function assertTenantWebsite(): void
    {
        abort_unless(tenancy()->initialized, 404);

        // "1-click enable" from the spec: defaults to true so existing tenants' sites keep
        // working unchanged; an admin can flip this off in Frontend settings to take it down.
        abort_if(data_get($this->frontendSettings(), 'is_enabled', true) === false, 404);
    }

    private function frontendSettings(): array
    {
        return TenantFrontendSetting::on('tenant')->where('key', 'site')->value('value') ?? [];
    }

    private function withVisitor(Request $request, array $data): array
    {
        return [
            ...$data,
            'visitor_ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ];
    }

    private function ensureDefaults(): void
    {
        TenantFrontendSetting::on('tenant')->firstOrCreate(['key' => 'site'], ['value' => [
            'primary_color' => '#0aa4e8',
            'accent_color' => '#8cc63f',
            'referral_offer' => 'Refer a friend and get a reward after successful connection.',
            'footer_about' => 'Ultra-high-speed broadband service for homes, students, gamers, offices, and businesses.',
        ]]);

        if (TenantFrontendSlider::on('tenant')->count() === 0) {
            foreach ([
                ['Get super charged with', 'Superfast Internet upto', 'Enjoy reliable fiber broadband for home and office.', '#packages'],
                ['Boost up your business', 'Business Booster', 'Dedicated bandwidth and prompt support for offices.', '#connection'],
                ['One internet that fits all', 'Unlimited Joy', 'Streaming, gaming, browsing, and family use in one plan.', '#packages'],
                ['Refer connection', 'Your Friend', 'Invite a friend and ask support about referral rewards.', '#referral'],
            ] as $index => [$subtitle, $title, $description, $url]) {
                TenantFrontendSlider::on('tenant')->create([
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'description' => $description,
                    'button_text' => $index === 1 ? 'Get Now' : 'Learn More',
                    'button_url' => $url,
                    'sort_order' => $index + 1,
                ]);
            }
        }

        foreach ([
            'intro' => ['#1 Broadband Internet Provider', 'Fast, reliable broadband for homes and businesses.'],
            'entertainment' => ['Your ticket to unlimited movies', 'Bundle entertainment, self-care, support, and payment access.'],
            'business_booster' => ['Business Booster', 'Dedicated connectivity options for serious work.'],
        ] as $key => [$title, $description]) {
            TenantFrontendSection::on('tenant')->firstOrCreate(['key' => $key], [
                'title' => $title,
                'description' => $description,
            ]);
        }

        if (TenantBlog::on('tenant')->count() === 0) {
            foreach (['Top Broadband Internet Provider', 'Experience Best Internet Service', 'Fastest Internet In Your Area'] as $title) {
                TenantBlog::on('tenant')->create([
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'excerpt' => 'Reliable speed, helpful support, and customer-friendly packages for modern homes and offices.',
                    'content' => 'Reliable speed, helpful support, and customer-friendly packages for modern homes and offices.',
                    'published_at' => now(),
                ]);
            }
        }
    }
}
