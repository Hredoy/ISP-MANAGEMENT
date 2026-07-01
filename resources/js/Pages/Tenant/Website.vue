<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
  BadgePercent,
  BriefcaseBusiness,
  Check,
  CreditCard,
  Gift,
  Headphones,
  MapPin,
  MessageSquareWarning,
  Phone,
  Rocket,
  Router,
  Send,
  ShieldCheck,
  Smartphone,
  Cable,
  Wifi,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  tenant: { type: Object, default: () => ({}) },
  packages: { type: Array, default: () => [] },
});

const submitted = ref(false);
const connectionForm = reactive({
  name: '',
  phone: '',
  area: '',
  package: '',
  message: '',
});

const displayPackages = computed(() => props.packages.length ? props.packages : [
  { name: 'Home 30', rate_limit: '30M/30M', price: 700, description: 'Smooth browsing and streaming' },
  { name: 'Family 50', rate_limit: '50M/50M', price: 800, description: 'Upto 100Mbps local boost' },
  { name: 'Power 100', rate_limit: '100M/100M', price: 1000, description: 'Gaming and smart home ready' },
  { name: 'Business 200', rate_limit: '200M/200M', price: 1500, description: 'Priority support and stable bandwidth' },
]);

const speedNumber = (rateLimit) => {
  const match = String(rateLimit ?? '').match(/\d+/);
  return match ? match[0] : '50';
};

const submitConnection = () => {
  submitted.value = true;
  connectionForm.name = '';
  connectionForm.phone = '';
  connectionForm.area = '';
  connectionForm.package = '';
  connectionForm.message = '';
};
</script>

<template>
  <Head :title="`${tenant.name} - Broadband Internet`" />

  <div class="min-h-screen bg-white text-slate-950">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-3">
          <div class="flex h-11 w-11 items-center justify-center rounded bg-sky-600 text-white">
            <Wifi class="h-6 w-6" />
          </div>
          <div>
            <p class="text-base font-black uppercase tracking-wide text-slate-950">{{ tenant.name }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ tenant.business_type }}</p>
          </div>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-black uppercase text-slate-700 lg:flex">
          <a href="#home" class="hover:text-sky-600">Home</a>
          <a href="#packages" class="hover:text-sky-600">Packages</a>
          <a href="#connection" class="hover:text-sky-600">New Connection</a>
          <a href="#payment" class="hover:text-sky-600">Payment</a>
          <a href="#complain" class="hover:text-sky-600">Complain</a>
          <a href="#contact" class="hover:text-sky-600">Contact</a>
        </nav>

        <div class="flex items-center gap-2">
          <a :href="`tel:${tenant.phone}`" class="hidden rounded bg-sky-600 px-3 py-2 text-sm font-black text-white sm:inline-flex">
            {{ tenant.phone }}
          </a>
          <Link :href="route('login')" class="rounded border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700">
            Self Care
          </Link>
        </div>
      </div>
    </header>

    <main id="home">
      <section class="relative overflow-hidden bg-slate-950">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(14,165,233,0.34),transparent_30%),radial-gradient(circle_at_80%_35%,rgba(34,197,94,0.18),transparent_28%)]"></div>
        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 text-white sm:px-6 lg:grid-cols-[1fr_.95fr] lg:px-8 lg:py-24">
          <div class="flex flex-col justify-center">
            <div class="mb-5 inline-flex w-fit items-center gap-2 rounded-full border border-sky-300/30 bg-sky-300/10 px-3 py-2 text-sm font-black text-sky-100">
              <Rocket class="h-4 w-4" />
              Superfast internet up to 200Mbps
            </div>
            <h1 class="max-w-3xl text-4xl font-black leading-tight sm:text-6xl">
              One internet that fits every home and business
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">
              Get reliable fiber broadband, business booster plans, online payment support, and fast customer care from {{ tenant.name }}.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
              <a href="#packages" class="rounded bg-sky-500 px-5 py-3 font-black text-white shadow-lg shadow-sky-950/40">
                View packages
              </a>
              <a href="#connection" class="rounded border border-white/20 px-5 py-3 font-black text-white hover:bg-white/10">
                Get new connection
              </a>
            </div>
          </div>

          <div class="relative">
            <div class="rounded border border-white/10 bg-white/10 p-4 backdrop-blur">
              <div class="rounded bg-white p-5 text-slate-950 shadow-2xl">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-xs font-black uppercase text-sky-600">Live network</p>
                    <h2 class="mt-1 text-2xl font-black">Broadband status</h2>
                  </div>
                  <Router class="h-8 w-8 text-sky-600" />
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                  <div class="rounded bg-slate-100 p-4">
                    <p class="text-3xl font-black text-sky-600">99%</p>
                    <p class="text-xs font-bold text-slate-500">Service uptime</p>
                  </div>
                  <div class="rounded bg-slate-100 p-4">
                    <p class="text-3xl font-black text-emerald-600">24/7</p>
                    <p class="text-xs font-bold text-slate-500">Support window</p>
                  </div>
                  <div class="col-span-2 rounded bg-slate-100 p-4">
                    <div class="mb-3 flex items-center justify-between">
                      <p class="text-sm font-black">Speed availability</p>
                      <p class="text-xs font-bold text-emerald-600">Healthy</p>
                    </div>
                    <div class="flex h-32 items-end gap-2">
                      <span v-for="height in [40, 72, 58, 104, 88, 122, 96, 135]" :key="height" class="flex-1 rounded-t bg-sky-500" :style="{ height: `${height}px` }"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="border-b border-slate-200 bg-sky-50 py-8">
        <div class="mx-auto grid max-w-7xl gap-3 px-4 sm:px-6 md:grid-cols-4 lg:px-8">
          <div class="flex items-center gap-3 rounded bg-white p-4 shadow-sm">
            <Cable class="h-7 w-7 text-sky-600" />
            <p class="font-black">Fiber optic connection</p>
          </div>
          <div class="flex items-center gap-3 rounded bg-white p-4 shadow-sm">
            <ShieldCheck class="h-7 w-7 text-sky-600" />
            <p class="font-black">Reliable bandwidth</p>
          </div>
          <div class="flex items-center gap-3 rounded bg-white p-4 shadow-sm">
            <Headphones class="h-7 w-7 text-sky-600" />
            <p class="font-black">Prompt support</p>
          </div>
          <div class="flex items-center gap-3 rounded bg-white p-4 shadow-sm">
            <Smartphone class="h-7 w-7 text-sky-600" />
            <p class="font-black">Self care access</p>
          </div>
        </div>
      </section>

      <section id="packages" class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="text-center">
            <p class="text-sm font-black uppercase text-sky-600">Our packages</p>
            <h2 class="mt-2 text-3xl font-black sm:text-4xl">Choose the speed that matches your life</h2>
          </div>

          <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div v-for="item in displayPackages" :key="item.name" class="rounded border border-slate-200 bg-white p-5 text-center shadow-sm">
              <p class="text-6xl font-black text-sky-600">{{ speedNumber(item.rate_limit) }}</p>
              <p class="mt-1 text-lg font-black">Mbps</p>
              <p class="mt-3 text-sm font-semibold text-slate-500">{{ item.description || item.rate_limit }}</p>
              <p class="mt-5 text-2xl font-black">{{ Number(item.price || 0).toLocaleString() }} BDT<span class="text-sm font-semibold text-slate-500">/month</span></p>
              <a href="#connection" class="mt-5 inline-flex rounded bg-slate-950 px-4 py-2 text-sm font-black text-white">
                Get now
              </a>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-slate-950 py-16 text-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[.85fr_1.15fr] lg:px-8">
          <div>
            <p class="text-sm font-black uppercase text-sky-300">Business booster</p>
            <h2 class="mt-2 text-4xl font-black">Dedicated plans for offices, branches and shops</h2>
            <p class="mt-4 leading-7 text-slate-300">
              Upgrade your operations with dedicated bandwidth, static IP, stable fiber connectivity, and priority support.
            </p>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div v-for="item in [
              ['Dedicated bandwidth', BriefcaseBusiness],
              ['Unlimited usage', Wifi],
              ['Static IP option', Router],
              ['Prompt support', Headphones],
            ]" :key="item[0]" class="rounded border border-white/10 bg-white/10 p-5">
              <component :is="item[1]" class="h-7 w-7 text-sky-300" />
              <p class="mt-4 text-lg font-black">{{ item[0] }}</p>
            </div>
          </div>
        </div>
      </section>

      <section id="connection" class="bg-slate-100 py-16">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[.8fr_1.2fr] lg:px-8">
          <div>
            <p class="text-sm font-black uppercase text-sky-600">New connection</p>
            <h2 class="mt-2 text-3xl font-black">Request a connection in your area</h2>
            <p class="mt-4 leading-7 text-slate-600">
              Send your details and our support team will contact you with availability, installation schedule, and package confirmation.
            </p>
            <div class="mt-6 rounded bg-white p-5 shadow-sm">
              <p class="flex items-center gap-2 font-black"><Phone class="h-5 w-5 text-sky-600" /> Hotline: {{ tenant.phone }}</p>
              <p class="mt-3 flex items-center gap-2 text-sm text-slate-600"><MapPin class="h-5 w-5 text-sky-600" /> {{ tenant.address }}</p>
            </div>
          </div>

          <form class="rounded border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submitConnection">
            <div class="grid gap-4 md:grid-cols-2">
              <input v-model="connectionForm.name" class="rounded border-slate-300" placeholder="Your name" required>
              <input v-model="connectionForm.phone" class="rounded border-slate-300" placeholder="Phone number" required>
              <input v-model="connectionForm.area" class="rounded border-slate-300" placeholder="Area / address" required>
              <select v-model="connectionForm.package" class="rounded border-slate-300" required>
                <option value="" disabled>Select package</option>
                <option v-for="item in displayPackages" :key="item.name" :value="item.name">{{ item.name }}</option>
              </select>
              <textarea v-model="connectionForm.message" class="rounded border-slate-300 md:col-span-2" rows="4" placeholder="Message"></textarea>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-3">
              <button type="submit" class="inline-flex items-center gap-2 rounded bg-sky-600 px-4 py-2 font-black text-white">
                <Send class="h-4 w-4" />
                Place request
              </button>
              <p v-if="submitted" class="text-sm font-bold text-emerald-700">Request noted. The support team will contact you.</p>
            </div>
          </form>
        </div>
      </section>

      <section id="payment" class="py-16">
        <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
          <div class="rounded bg-sky-600 p-6 text-white">
            <Gift class="h-8 w-8" />
            <h3 class="mt-5 text-2xl font-black">Refer a friend</h3>
            <p class="mt-2 text-sm text-sky-50">Invite someone to join and ask support about referral rewards.</p>
          </div>
          <div class="rounded bg-slate-950 p-6 text-white">
            <CreditCard class="h-8 w-8" />
            <h3 class="mt-5 text-2xl font-black">Recharge and payment</h3>
            <p class="mt-2 text-sm text-slate-300">Pay monthly bills through supported channels and keep service active.</p>
          </div>
          <div id="complain" class="rounded bg-amber-400 p-6 text-slate-950">
            <MessageSquareWarning class="h-8 w-8" />
            <h3 class="mt-5 text-2xl font-black">Place complain</h3>
            <p class="mt-2 text-sm font-semibold">Report downtime, slow speed, or support needs with your customer ID.</p>
          </div>
        </div>
      </section>
    </main>

    <footer id="contact" class="bg-slate-950 py-10 text-white">
      <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
        <div>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded bg-sky-600">
              <Wifi class="h-5 w-5" />
            </div>
            <p class="font-black uppercase">{{ tenant.name }}</p>
          </div>
          <p class="mt-4 text-sm leading-6 text-slate-300">
            Fast, reliable broadband internet for homes, offices, and growing businesses.
          </p>
        </div>
        <div>
          <p class="font-black uppercase">Contact</p>
          <p class="mt-4 text-sm text-slate-300">{{ tenant.phone }}</p>
          <p class="mt-2 text-sm text-slate-300">{{ tenant.email }}</p>
          <p class="mt-2 text-sm text-slate-300">{{ tenant.address }}</p>
        </div>
        <div>
          <p class="font-black uppercase">Quick links</p>
          <div class="mt-4 flex flex-col gap-2 text-sm text-slate-300">
            <a href="#packages">Packages</a>
            <a href="#connection">New connection</a>
            <a href="#payment">Payment</a>
            <a href="#complain">Complain</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>
