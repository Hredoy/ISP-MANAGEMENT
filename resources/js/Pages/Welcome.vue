<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
  Activity,
  BadgeCheck,
  Banknote,
  BarChart3,
  Cable,
  Check,
  CreditCard,
  Headphones,
  LockKeyhole,
  Network,
  RadioTower,
  ReceiptText,
  Send,
  ShieldCheck,
  Smartphone,
  TicketCheck,
  Users,
  Wifi,
} from 'lucide-vue-next';

defineProps({
  canLogin: Boolean,
  canRegister: Boolean,
  modules: { type: Array, default: () => [] },
});

const form = useForm({
  organization_name: '',
  owner_name: '',
  email: '',
  phone: '',
  address: '',
  domain_request: '',
  business_type: 'ISP / Broadband',
  package_request: 'Standard',
  module_request: [],
  notes: '',
});

const features = [
  { title: 'Subscriber CRM', text: 'Customers, zones, packages, tickets, and connection history in one operating view.', icon: Users },
  { title: 'Billing Automation', text: 'Monthly invoices, dues, collections, payment tracking, and service lifecycle controls.', icon: ReceiptText },
  { title: 'MikroTik Ready', text: 'Router profiles, PPPoE package sync, live checks, and network device records.', icon: RadioTower },
  { title: 'Payment Operations', text: 'Cash, mobile banking, gateways, receipts, and daily collection visibility.', icon: CreditCard },
  { title: 'Reports & Accounts', text: 'Business, billing, collection, and accounting reports built for ISP owners.', icon: BarChart3 },
  { title: 'Tenant Modules', text: 'Landlord-controlled modules, tenant status, domains, database setup, and provisioning logs.', icon: ShieldCheck },
];

const plans = [
  { name: 'Starter', price: '৳2,000', users: 'Up to 300 subscribers', modules: ['Customers', 'Packages', 'Billing', 'Payments'] },
  { name: 'Standard', price: '৳3,000', users: 'Up to 1,000 subscribers', modules: ['MikroTik', 'Support', 'SMS', 'Reports'] },
  { name: 'Premium', price: '৳5,000', users: 'Multi-branch operations', modules: ['Accounting', 'Inventory', 'Employees', 'Settings'] },
];

const stats = [
  ['99.9%', 'Tenant uptime focus'],
  ['12+', 'ISP modules'],
  ['24/7', 'Operational visibility'],
  ['BDT', 'Billing localization'],
];

const submit = () => {
  form.post(route('tenant.apply.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
};
</script>

<template>
  <Head title="ISP Management SaaS" />

  <div class="min-h-screen bg-slate-950 text-white">
    <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/90 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded bg-cyan-400 text-slate-950">
            <Wifi class="h-5 w-5" />
          </div>
          <div>
            <p class="text-sm font-black uppercase tracking-wide">ISP Management</p>
            <p class="text-xs text-slate-400">Billing and tenant SaaS</p>
          </div>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-300 md:flex">
          <a href="#features" class="hover:text-white">Features</a>
          <a href="#pricing" class="hover:text-white">Pricing</a>
          <a href="#apply" class="hover:text-white">Apply</a>
        </nav>

        <div v-if="canLogin" class="flex items-center gap-2">
          <Link v-if="$page.props.auth.user" :href="route('landlord.dashboard')" class="rounded bg-white px-3 py-2 text-sm font-bold text-slate-950">
            Dashboard
          </Link>
          <template v-else>
            <Link :href="route('login')" class="rounded border border-white/20 px-3 py-2 text-sm font-bold text-white hover:bg-white/10">
              Login
            </Link>
            <Link v-if="canRegister" :href="route('register')" class="hidden rounded bg-cyan-400 px-3 py-2 text-sm font-bold text-slate-950 sm:inline-flex">
              Register
            </Link>
          </template>
        </div>
      </div>
    </header>

    <main>
      <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,0.22),transparent_28%),radial-gradient(circle_at_80%_10%,rgba(16,185,129,0.18),transparent_28%)]"></div>
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.02fr_.98fr] lg:px-8 lg:py-24">
          <div class="flex flex-col justify-center">
            <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-cyan-300/30 bg-cyan-300/10 px-3 py-2 text-sm font-bold text-cyan-100">
              <BadgeCheck class="h-4 w-4" />
              Built for ISP billing, CRM, network and tenant operations
            </div>
            <h1 class="max-w-3xl text-4xl font-black leading-tight text-white sm:text-5xl lg:text-6xl">
              ISP management software for fast-moving broadband teams
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">
              Manage customers, packages, billing, MikroTik, support tickets, payment collection, reports, and tenant provisioning from one clean SaaS platform.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <a href="#apply" class="inline-flex items-center gap-2 rounded bg-cyan-400 px-5 py-3 font-black text-slate-950 shadow-lg shadow-cyan-950/40">
                <Send class="h-4 w-4" />
                Request tenant access
              </a>
              <a href="#features" class="inline-flex items-center gap-2 rounded border border-white/20 px-5 py-3 font-bold text-white hover:bg-white/10">
                Explore modules
              </a>
            </div>
            <div class="mt-10 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
              <div v-for="[value, label] in stats" :key="label" class="rounded border border-white/10 bg-white/5 p-4">
                <p class="text-2xl font-black text-white">{{ value }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-400">{{ label }}</p>
              </div>
            </div>
          </div>

          <div class="relative">
            <div class="rounded border border-white/10 bg-white/10 p-3 shadow-2xl shadow-cyan-950/30 backdrop-blur">
              <div class="overflow-hidden rounded bg-slate-900 ring-1 ring-white/10">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                  <div class="flex gap-2">
                    <span class="h-3 w-3 rounded-full bg-red-400"></span>
                    <span class="h-3 w-3 rounded-full bg-amber-300"></span>
                    <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                  </div>
                  <span class="text-xs font-bold text-cyan-200">LIVE ISP OPS</span>
                </div>
                <div class="grid gap-4 p-4 md:grid-cols-[.8fr_1.2fr]">
                  <aside class="space-y-2">
                    <div v-for="item in ['Dashboard', 'Customers', 'Packages', 'Billing', 'MikroTik', 'Reports']" :key="item" class="rounded border border-white/10 px-3 py-2 text-xs font-bold text-slate-300" :class="item === 'Dashboard' ? 'bg-cyan-400 text-slate-950' : 'bg-white/5'">
                      {{ item }}
                    </div>
                  </aside>
                  <div class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                      <div class="rounded bg-slate-800 p-3">
                        <Activity class="h-4 w-4 text-emerald-300" />
                        <p class="mt-4 text-xl font-black">1,284</p>
                        <p class="text-[11px] text-slate-400">Active clients</p>
                      </div>
                      <div class="rounded bg-slate-800 p-3">
                        <Banknote class="h-4 w-4 text-cyan-300" />
                        <p class="mt-4 text-xl font-black">৳4.8M</p>
                        <p class="text-[11px] text-slate-400">Collections</p>
                      </div>
                      <div class="rounded bg-slate-800 p-3">
                        <Network class="h-4 w-4 text-amber-300" />
                        <p class="mt-4 text-xl font-black">98%</p>
                        <p class="text-[11px] text-slate-400">Online PPPoE</p>
                      </div>
                    </div>
                    <div class="rounded bg-slate-800 p-4">
                      <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-bold">Daily billing flow</p>
                        <p class="text-xs text-emerald-300">Healthy</p>
                      </div>
                      <div class="flex h-36 items-end gap-2">
                        <span v-for="height in [42, 66, 58, 90, 74, 110, 86, 130, 105, 122]" :key="height" class="flex-1 rounded-t bg-cyan-400/80" :style="{ height: `${height}px` }"></span>
                      </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                      <div class="rounded bg-emerald-400/10 p-3 text-sm font-bold text-emerald-200">Tenant DB ready</div>
                      <div class="rounded bg-cyan-400/10 p-3 text-sm font-bold text-cyan-200">Domain active</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="features" class="bg-white py-16 text-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="max-w-3xl">
            <p class="text-sm font-black uppercase text-cyan-700">Core features</p>
            <h2 class="mt-2 text-3xl font-black sm:text-4xl">Everything an ISP operator expects from a serious management platform</h2>
          </div>
          <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div v-for="feature in features" :key="feature.title" class="rounded border border-slate-200 bg-white p-5 shadow-sm">
              <component :is="feature.icon" class="h-6 w-6 text-cyan-700" />
              <h3 class="mt-4 text-lg font-black">{{ feature.title }}</h3>
              <p class="mt-2 text-sm leading-6 text-slate-600">{{ feature.text }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-slate-100 py-16 text-slate-950">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-4 lg:px-8">
          <div class="lg:col-span-1">
            <p class="text-sm font-black uppercase text-cyan-700">Accessible portals</p>
            <h2 class="mt-2 text-3xl font-black">Admin, employee, customer and reseller workflows</h2>
          </div>
          <div class="grid gap-4 md:grid-cols-3 lg:col-span-3">
            <div class="rounded border border-slate-200 bg-white p-5">
              <Smartphone class="h-6 w-6 text-cyan-700" />
              <h3 class="mt-4 font-black">Mobile-friendly operations</h3>
              <p class="mt-2 text-sm text-slate-600">Collect bills, view subscribers, and track field tasks from responsive screens.</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-5">
              <Headphones class="h-6 w-6 text-cyan-700" />
              <h3 class="mt-4 font-black">Support desk</h3>
              <p class="mt-2 text-sm text-slate-600">Tickets, customer communication, service issues, and follow-up status.</p>
            </div>
            <div class="rounded border border-slate-200 bg-white p-5">
              <Cable class="h-6 w-6 text-cyan-700" />
              <h3 class="mt-4 font-black">Network visibility</h3>
              <p class="mt-2 text-sm text-slate-600">Router information, package plans, connectivity checks, and operational metrics.</p>
            </div>
          </div>
        </div>
      </section>

      <section id="pricing" class="bg-white py-16 text-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
              <p class="text-sm font-black uppercase text-cyan-700">Packages</p>
              <h2 class="mt-2 text-3xl font-black">Choose a package request for your organization</h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-slate-600">Final pricing can vary by subscriber volume, module scope, custom domain, and implementation needs.</p>
          </div>
          <div class="mt-8 grid gap-4 lg:grid-cols-3">
            <div v-for="plan in plans" :key="plan.name" class="rounded border border-slate-200 p-5" :class="plan.name === 'Standard' ? 'bg-slate-950 text-white shadow-xl' : 'bg-white'">
              <p class="text-lg font-black">{{ plan.name }}</p>
              <p class="mt-3 text-3xl font-black">{{ plan.price }}<span class="text-sm font-semibold opacity-70"> /mo</span></p>
              <p class="mt-2 text-sm opacity-70">{{ plan.users }}</p>
              <div class="mt-5 space-y-2">
                <p v-for="item in plan.modules" :key="item" class="flex items-center gap-2 text-sm font-semibold">
                  <Check class="h-4 w-4 text-cyan-500" />
                  {{ item }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="apply" class="bg-slate-950 py-16">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[.8fr_1.2fr] lg:px-8">
          <div>
            <p class="text-sm font-black uppercase text-cyan-300">Request access</p>
            <h2 class="mt-2 text-3xl font-black sm:text-4xl">Apply for your tenant workspace</h2>
            <p class="mt-4 leading-7 text-slate-300">
              Submit your organization details. The landlord team can approve your application, provision an isolated database, seed defaults, assign modules, and activate your domain.
            </p>
            <div class="mt-6 space-y-3 text-sm font-semibold text-slate-300">
              <p class="flex items-center gap-2"><LockKeyhole class="h-4 w-4 text-cyan-300" /> Isolated tenant database after approval</p>
              <p class="flex items-center gap-2"><TicketCheck class="h-4 w-4 text-cyan-300" /> Provisioning logs for each setup step</p>
              <p class="flex items-center gap-2"><ShieldCheck class="h-4 w-4 text-cyan-300" /> Landlord-controlled feature modules</p>
            </div>
          </div>

          <form class="rounded border border-white/10 bg-white p-5 text-slate-950 shadow-2xl" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
              <label class="block">
                <span class="text-sm font-bold">Organization name</span>
                <input v-model="form.organization_name" class="mt-1 w-full rounded border-slate-300" type="text" required>
                <p class="mt-1 text-xs text-red-600">{{ form.errors.organization_name }}</p>
              </label>
              <label class="block">
                <span class="text-sm font-bold">Owner name</span>
                <input v-model="form.owner_name" class="mt-1 w-full rounded border-slate-300" type="text" required>
                <p class="mt-1 text-xs text-red-600">{{ form.errors.owner_name }}</p>
              </label>
              <label class="block">
                <span class="text-sm font-bold">Email</span>
                <input v-model="form.email" class="mt-1 w-full rounded border-slate-300" type="email" required>
                <p class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
              </label>
              <label class="block">
                <span class="text-sm font-bold">Phone</span>
                <input v-model="form.phone" class="mt-1 w-full rounded border-slate-300" type="tel" required>
                <p class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
              </label>
              <label class="block md:col-span-2">
                <span class="text-sm font-bold">Address</span>
                <input v-model="form.address" class="mt-1 w-full rounded border-slate-300" type="text">
              </label>
              <label class="block">
                <span class="text-sm font-bold">Domain/subdomain request</span>
                <input v-model="form.domain_request" class="mt-1 w-full rounded border-slate-300" type="text" placeholder="yourisp or yourisp.com">
              </label>
              <label class="block">
                <span class="text-sm font-bold">Business type</span>
                <input v-model="form.business_type" class="mt-1 w-full rounded border-slate-300" type="text">
              </label>
              <label class="block md:col-span-2">
                <span class="text-sm font-bold">Package request</span>
                <select v-model="form.package_request" class="mt-1 w-full rounded border-slate-300">
                  <option>Starter</option>
                  <option>Standard</option>
                  <option>Premium</option>
                  <option>Custom</option>
                </select>
              </label>
            </div>

            <div class="mt-5">
              <p class="text-sm font-bold">Requested modules</p>
              <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="module in modules" :key="module.slug" class="inline-flex items-center gap-2 rounded border border-slate-200 px-3 py-2 text-sm">
                  <input v-model="form.module_request" type="checkbox" :value="module.slug" class="rounded border-slate-300">
                  {{ module.name }}
                </label>
              </div>
              <p class="mt-1 text-xs text-red-600">{{ form.errors.module_request }}</p>
            </div>

            <label class="mt-5 block">
              <span class="text-sm font-bold">Notes/message</span>
              <textarea v-model="form.notes" class="mt-1 w-full rounded border-slate-300" rows="4" placeholder="Tell us about your subscriber count, branches, router setup, or migration needs."></textarea>
            </label>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <button type="submit" class="inline-flex items-center gap-2 rounded bg-cyan-500 px-4 py-2 font-black text-slate-950 disabled:opacity-60" :disabled="form.processing">
                <Send class="h-4 w-4" />
                {{ form.processing ? 'Submitting...' : 'Submit application' }}
              </button>
              <p v-if="$page.props.flash.success" class="text-sm font-bold text-emerald-700">{{ $page.props.flash.success }}</p>
            </div>
          </form>
        </div>
      </section>
    </main>

    <footer class="border-t border-white/10 bg-slate-950 px-4 py-8 text-center text-sm text-slate-400">
      ISP Management SaaS · Tenant provisioning, billing, CRM, and network operations
    </footer>
  </div>
</template>
