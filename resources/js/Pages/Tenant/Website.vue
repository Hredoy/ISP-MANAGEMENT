<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
  BadgePercent,
  BriefcaseBusiness,
  Cable,
  CreditCard,
  Gift,
  Headphones,
  MapPin,
  MessageSquareWarning,
  MonitorPlay,
  RadioTower,
  Rocket,
  Router,
  Send,
  ShieldCheck,
  Smartphone,
  Wifi,
  X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  tenant: { type: Object, default: () => ({}) },
  frontend: { type: Object, default: () => ({}) },
  sliders: { type: Array, default: () => [] },
  blogs: { type: Array, default: () => [] },
  sections: { type: Object, default: () => ({}) },
  packages: { type: Array, default: () => [] },
  openModal: { type: String, default: null },
});

const page = usePage();
const activeModal = ref(null);
const mobileMenuOpen = ref(false);
const paymentOpen = ref(false);
const connectionForm = useForm({
  website: '',
  name: '',
  phone: '',
  address: '',
  area: '',
  package: '',
  preferred_connection_date: '',
  message: '',
});
const complaintForm = useForm({
  website: '',
  customer_id: '',
  phone: '',
  complaint_type: '',
  message: '',
  image: null,
});
const referralForm = useForm({
  website: '',
  friend_name: '',
  friend_phone: '',
  referrer_user_id: '',
});
const paymentForm = useForm({
  website: '',
  customer_id: '',
  amount: '',
  transaction_id: '',
  payment_method: '',
  screenshot: null,
});

const speedNumber = (rateLimit) => {
  const match = String(rateLimit ?? '').match(/\d+/);
  return match ? match[0] : '50';
};

const displayPackages = computed(() => props.packages.length ? props.packages : [
  { name: 'Home 30', rate_limit: '30M/30M', price: 700, description: '2,100 BDT / 3 months' },
  { name: 'Joy 50', rate_limit: '50M/50M', price: 800, description: 'Upto 100Mbps local boost' },
  { name: 'Turbo 100', rate_limit: '100M/100M', price: 1000, description: 'Upto 500Mbps local boost' },
  { name: 'Ultra 200', rate_limit: '200M/200M', price: 1500, description: 'Upto 1Gbps local boost' },
]);

const maxSpeed = computed(() => Math.max(...displayPackages.value.map((item) => Number(speedNumber(item.rate_limit)))));
const heroSlides = computed(() => props.sliders.length
  ? props.sliders.map((slide) => [slide.subtitle, slide.title, slide.button_text || `${maxSpeed.value}Mbps`, slide.description, slide.button_url || '#packages'])
  : [
    ['Get super charged with', 'Superfast Internet upto', `${maxSpeed.value}Mbps`, `Enjoy low-latency fiber broadband from ${props.tenant.name}.`, '#packages'],
    ['Boost up your business', `Get ${props.tenant.name}`, 'Business Booster', 'Dedicated bandwidth, static IP options, and prompt support for offices.', '#connection'],
    ['One internet that fits all', 'A bundle of', 'Unlimited Joy', 'Streaming, gaming, browsing, online class, and family use in one plan.', '#packages'],
    [`Refer ${props.tenant.name}`, 'Connection to', 'Your Friend', 'Invite a friend and ask support about referral rewards.', '#referral'],
  ]);

const blogItems = computed(() => props.blogs.length ? props.blogs : [
  { title: 'Top Broadband Internet Provider', excerpt: 'Reliable speed, helpful support, and customer-friendly packages for modern homes and offices.', slug: 'top-broadband-internet-provider' },
  { title: 'Experience Best Internet Service', excerpt: 'Reliable speed, helpful support, and customer-friendly packages for modern homes and offices.', slug: 'experience-best-internet-service' },
  { title: 'Fastest Internet In Your Area', excerpt: 'Reliable speed, helpful support, and customer-friendly packages for modern homes and offices.', slug: 'fastest-internet-in-your-area' },
]);

const primaryColor = computed(() => props.frontend.primary_color || '#0aa4e8');
const accentColor = computed(() => props.frontend.accent_color || '#8cc63f');
const introSection = computed(() => props.sections.intro || {});
const entertainmentSection = computed(() => props.sections.entertainment || {});

const submitConnection = () => {
  connectionForm.post(route('tenant.website.connection.store'), {
    preserveScroll: true,
    onSuccess: () => {
      connectionForm.reset();
      activeModal.value = null;
    },
  });
};

const submitComplaint = () => complaintForm.post(route('tenant.website.complaints.store'), {
  preserveScroll: true,
  forceFormData: true,
  onSuccess: () => {
    complaintForm.reset();
    activeModal.value = null;
  },
});

const submitReferral = () => referralForm.post(route('tenant.website.referrals.store'), {
  preserveScroll: true,
  onSuccess: () => {
    referralForm.reset();
    activeModal.value = null;
  },
});

const submitPayment = () => paymentForm.post(route('tenant.website.manual-payments.store'), {
  preserveScroll: true,
  forceFormData: true,
  onSuccess: () => {
    paymentForm.reset();
    activeModal.value = null;
  },
});

onMounted(() => {
  if (props.openModal) {
    activeModal.value = props.openModal;
  }
});
</script>

<template>
  <Head>
    <title>{{ frontend.seo_title || `${tenant.name} - Broadband Internet` }}</title>
    <meta name="description" :content="frontend.seo_description || tenant.about || `${tenant.name} broadband internet service`">
    <meta name="keywords" :content="frontend.seo_keywords || `${tenant.name}, broadband, internet, ISP`">
    <meta property="og:title" :content="frontend.seo_title || `${tenant.name} - Broadband Internet`">
    <meta property="og:description" :content="frontend.seo_description || tenant.about || `${tenant.name} broadband internet service`">
    <meta v-if="frontend.og_image" property="og:image" :content="frontend.og_image">
  </Head>

  <div class="min-h-screen bg-white text-slate-950" :style="{ '--primary': primaryColor, '--accent': accentColor }">
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/20 bg-white/95 shadow-sm backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-3">
          <img v-if="tenant.logo" :src="tenant.logo" :alt="tenant.name" class="h-12 w-12 rounded object-contain">
          <div v-else class="relative flex h-12 w-12 items-center justify-center rounded bg-[var(--primary)] text-white shadow-lg shadow-sky-200">
            <Wifi class="h-6 w-6" />
            <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-lime-400 ring-2 ring-white"></span>
          </div>
          <div>
            <p class="text-lg font-black uppercase leading-none tracking-wide text-[#0c315f]">{{ tenant.name }}</p>
            <p class="mt-1 text-xs font-bold uppercase text-slate-500">Broadband Internet Provider</p>
          </div>
        </a>

        <nav class="hidden items-center gap-6 text-sm font-black uppercase text-slate-700 lg:flex">
          <a href="#home" class="hover:text-[#0aa4e8]">Home</a>
          <a href="/packages" class="hover:text-[var(--primary)]">Packages</a>
          <button type="button" class="hover:text-[#0aa4e8]" @click="activeModal = 'connection'">New Connection</button>
          <button type="button" class="hover:text-[#0aa4e8]" @click="activeModal = 'referral'">Referral</button>
          <div class="relative">
            <button type="button" class="hover:text-[var(--primary)]" @click="paymentOpen = !paymentOpen">Payment</button>
            <div v-if="paymentOpen" class="absolute right-0 top-8 w-48 rounded-xl bg-white p-2 text-left shadow-xl ring-1 ring-slate-200">
              <button type="button" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-100" @click="activeModal = 'payment'; paymentOpen = false">Online Payment</button>
              <button type="button" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-100" @click="activeModal = 'payment'; paymentOpen = false">Manual Payment</button>
            </div>
          </div>
          <button type="button" class="hover:text-[#0aa4e8]" @click="activeModal = 'complain'">Complain</button>
          <a href="#contact" class="hover:text-[#0aa4e8]">Contact</a>
        </nav>

        <div class="flex items-center gap-2">
          <a :href="`tel:${tenant.phone}`" class="hidden rounded-full bg-[var(--accent)] px-4 py-2 text-sm font-black text-white shadow-md sm:inline-flex">
            {{ tenant.phone }}
          </a>
          <Link :href="route('login')" class="rounded-full border border-[#0aa4e8]/30 px-4 py-2 text-sm font-black text-[#0c315f]">
            Self Care
          </Link>
          <button type="button" class="rounded border border-slate-200 px-3 py-2 text-sm font-black lg:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
            Menu
          </button>
        </div>
      </div>
      <div v-if="mobileMenuOpen" class="border-t border-slate-100 bg-white px-4 py-3 lg:hidden">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 text-sm font-black uppercase text-slate-700">
          <a href="#home" @click="mobileMenuOpen = false">Home</a>
          <a href="/packages" @click="mobileMenuOpen = false">Packages</a>
          <button type="button" class="w-fit" @click="activeModal = 'connection'; mobileMenuOpen = false">New Connection</button>
          <button type="button" class="w-fit" @click="activeModal = 'referral'; mobileMenuOpen = false">Referral</button>
          <button type="button" class="w-fit" @click="activeModal = 'payment'; mobileMenuOpen = false">Payment</button>
          <button type="button" class="w-fit" @click="activeModal = 'complain'; mobileMenuOpen = false">Complain</button>
          <a href="/contact" @click="mobileMenuOpen = false">Contact</a>
        </div>
      </div>
    </header>

    <main id="home" class="pt-[73px]">
      <section class="relative min-h-[700px] overflow-hidden bg-[#052b55] text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(0,177,235,0.34),transparent_30%),radial-gradient(circle_at_80%_25%,rgba(140,198,63,0.28),transparent_26%),linear-gradient(135deg,#073465_0%,#061d3c_62%,#0b315f_100%)]"></div>
        <div class="packet packet-a"></div>
        <div class="packet packet-b"></div>
        <div class="packet packet-c"></div>
        <div class="cloud cloud-a"></div>
        <div class="cloud cloud-b"></div>

        <div class="relative mx-auto grid min-h-[700px] max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
          <div class="relative h-[340px] sm:h-[390px]">
            <article
              v-for="(slide, index) in heroSlides"
              :key="slide[2]"
              class="hero-slide absolute inset-0 flex flex-col justify-center"
              :style="{ animationDelay: `${index * 5}s` }"
            >
              <p class="text-xl font-black uppercase text-[#8cc63f] sm:text-2xl">{{ slide[0] }}</p>
              <h1 class="mt-3 text-4xl font-black uppercase leading-tight sm:text-6xl">
                {{ slide[1] }}
                <span class="block text-[#23b7ed]">{{ slide[2] }}</span>
              </h1>
              <p class="mt-5 max-w-xl text-lg leading-8 text-sky-100">{{ slide[3] }}</p>
              <div class="mt-8 flex flex-wrap gap-3">
                <a :href="slide[4] || '#packages'" class="rounded-full bg-[var(--accent)] px-6 py-3 text-sm font-black uppercase text-white shadow-xl shadow-lime-950/30">
                  Learn More
                </a>
                <button type="button" class="rounded-full border border-white/30 px-6 py-3 text-sm font-black uppercase text-white" @click="activeModal = 'connection'">
                  Get Now
                </button>
              </div>
            </article>
          </div>

          <div class="relative min-h-[520px]">
            <div class="orbit orbit-one"></div>
            <div class="orbit orbit-two"></div>
            <div class="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#23b7ed]/20 blur-3xl"></div>
            <div class="device-mock absolute left-1/2 top-10 w-[310px] -translate-x-1/2 rounded-[2rem] border-8 border-white bg-slate-950 p-4 shadow-2xl sm:w-[360px]">
              <div class="rounded-[1.3rem] bg-white p-4 text-slate-950">
                <div class="mb-4 flex items-center justify-between">
                  <p class="text-xs font-black uppercase text-[#0aa4e8]">Self Care</p>
                  <RadioTower class="h-5 w-5 text-[#8cc63f]" />
                </div>
                <div class="rounded bg-[#0aa4e8] p-4 text-white">
                  <p class="text-sm font-bold">Current Package</p>
                  <p class="mt-1 text-4xl font-black">{{ speedNumber(displayPackages[0]?.rate_limit) }} Mbps</p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                  <div class="rounded bg-slate-100 p-3">
                    <p class="text-xl font-black text-[#8cc63f]">PAID</p>
                    <p class="text-xs font-bold text-slate-500">Bill status</p>
                  </div>
                  <div class="rounded bg-slate-100 p-3">
                    <p class="text-xl font-black text-[#0aa4e8]">99%</p>
                    <p class="text-xs font-bold text-slate-500">Uptime</p>
                  </div>
                </div>
                <div class="mt-4 flex h-28 items-end gap-2 rounded bg-slate-100 p-3">
                  <span v-for="height in [42, 70, 52, 92, 78, 104, 64]" :key="height" class="flex-1 rounded-t bg-[#23b7ed]" :style="{ height: `${height}px` }"></span>
                </div>
              </div>
            </div>
            <div class="speed-badge absolute bottom-12 left-6 rounded-2xl bg-white px-5 py-4 text-[#0c315f] shadow-2xl">
              <p class="text-xs font-black uppercase text-slate-400">Speed upto</p>
              <p class="text-4xl font-black text-[#0aa4e8]">{{ maxSpeed }}<span class="text-lg">Mbps</span></p>
            </div>
            <div class="support-badge absolute right-4 top-16 rounded-2xl bg-[#8cc63f] px-5 py-4 text-white shadow-2xl">
              <p class="text-3xl font-black">24/7</p>
              <p class="text-xs font-black uppercase">Support</p>
            </div>
          </div>
        </div>

        <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 gap-2">
          <span v-for="index in 4" :key="index" class="hero-dot" :style="{ animationDelay: `${(index - 1) * 5}s` }"></span>
        </div>
      </section>

      <section class="bg-white py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="grid gap-8 lg:grid-cols-[.85fr_1.15fr]">
            <div class="rounded-[2rem] bg-[#f2f8fb] p-8">
              <p class="text-lg font-black uppercase text-[var(--accent)]">{{ introSection.title || '#1 Top Broadband Internet Provider' }}</p>
              <h2 class="mt-3 text-4xl font-black leading-tight text-[#0c315f]">{{ introSection.description || `${tenant.name} connects your city with faster fiber internet` }}</h2>
            </div>
            <div class="space-y-4 text-base leading-8 text-slate-600">
              <p>{{ tenant.about || `${tenant.name} delivers high-speed broadband for homes, gamers, offices, shops, and growing teams. Our network is built for reliable browsing, streaming, online classes, payments, and business tools.` }}</p>
              <p>Choose the best package for your usage, request a new connection, recharge online, place a complain, or refer a friend from this customer portal.</p>
            </div>
          </div>
        </div>
      </section>

      <section id="packages" class="relative overflow-hidden bg-[#f5fbff] py-16">
        <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#0aa4e8] via-[#8cc63f] to-[#0aa4e8]"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="text-center">
            <p class="text-sm font-black uppercase tracking-[0.28em] text-[#8cc63f]">Our Packages</p>
            <h2 class="mt-3 text-4xl font-black uppercase text-[#0c315f]">Pick your speed</h2>
          </div>

          <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <div v-for="item in displayPackages" :key="item.name" class="package-card group relative overflow-hidden rounded-[1.6rem] bg-white p-6 text-center shadow-lg ring-1 ring-sky-100">
              <div class="absolute inset-x-0 top-0 h-2 bg-[#0aa4e8] transition group-hover:bg-[#8cc63f]"></div>
              <p class="text-7xl font-black leading-none text-[#0aa4e8] transition group-hover:scale-110 group-hover:text-[#8cc63f]">{{ speedNumber(item.rate_limit) }}</p>
              <p class="mt-1 text-xl font-black uppercase text-[#0c315f]">Mbps</p>
              <p class="mt-4 min-h-10 text-sm font-bold text-slate-500">{{ item.description || item.rate_limit }}</p>
              <p class="mt-6 text-3xl font-black text-[#0c315f]">{{ Number(item.price || 0).toLocaleString() }} <span class="text-sm text-slate-500">BDT/month</span></p>
              <button type="button" class="mt-6 rounded-full bg-[#0c315f] px-5 py-3 text-sm font-black uppercase text-white transition group-hover:bg-[#0aa4e8]" @click="connectionForm.package = item.name; activeModal = 'connection'">
                Get Now!
              </button>
            </div>
          </div>
        </div>
      </section>

      <section class="overflow-hidden bg-white py-16">
        <div class="mx-auto grid max-w-7xl items-center gap-8 px-4 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:px-8">
          <div class="relative min-h-[360px] rounded-[2rem] bg-[#0aa4e8] p-8 text-white shadow-xl">
            <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/20"></div>
            <div class="absolute bottom-4 right-8 flex h-40 w-40 items-center justify-center rounded-full bg-white text-[#0aa4e8] shadow-2xl">
              <Gift class="h-20 w-20 gift-shake" />
            </div>
            <p class="text-2xl font-black uppercase text-lime-200">Refer</p>
            <h2 class="mt-3 max-w-md text-5xl font-black uppercase leading-tight">Connection to your friend</h2>
            <button type="button" class="mt-8 rounded-full bg-[#8cc63f] px-6 py-3 text-sm font-black uppercase text-white" @click="activeModal = 'referral'">
              Refer Now
            </button>
          </div>

          <div class="space-y-6">
            <h2 class="text-4xl font-black uppercase text-[#0c315f]">Why customers choose {{ tenant.name }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
              <div v-for="item in [
                ['Fast installation', Rocket],
                ['Reliable fiber', Cable],
                ['Prompt support', Headphones],
                ['Easy recharge', CreditCard],
              ]" :key="item[0]" class="rounded-2xl bg-[#f5fbff] p-5 shadow-sm">
                <component :is="item[1]" class="h-8 w-8 text-[#0aa4e8]" />
                <p class="mt-4 text-lg font-black text-[#0c315f]">{{ item[0] }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="relative overflow-hidden bg-[#072b55] py-16 text-white">
        <div class="absolute left-0 top-0 h-full w-full bg-[radial-gradient(circle_at_15%_20%,rgba(35,183,237,0.32),transparent_28%)]"></div>
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="grid gap-8 lg:grid-cols-[.85fr_1.15fr]">
            <div>
              <p class="text-sm font-black uppercase tracking-[0.28em] text-[#8cc63f]">Business Booster</p>
              <h2 class="mt-3 text-5xl font-black uppercase leading-tight">Get {{ tenant.name }} business booster</h2>
              <p class="mt-5 leading-8 text-sky-100">Dedicated connectivity options for offices, branches, shops, CCTV, IP telephony, and business-critical work.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-5">
              <div v-for="item in [
                ['Dedicated Bandwidth', BriefcaseBusiness],
                ['Unlimited Usage', Wifi],
                ['Static IP', Router],
                ['Fiber Optic Connection', Cable],
                ['Prompt Support', Headphones],
              ]" :key="item[0]" class="booster-card rounded-2xl bg-white p-4 text-center text-[#0c315f] shadow-xl">
                <component :is="item[1]" class="mx-auto h-9 w-9 text-[#0aa4e8]" />
                <p class="mt-4 text-xs font-black uppercase leading-5">{{ item[0] }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-[#f5fbff] py-16">
        <div class="mx-auto grid max-w-7xl gap-5 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
          <button type="button" class="action-tile bg-[#0aa4e8] text-white" @click="activeModal = 'payment'">
            <CreditCard class="h-10 w-10" />
            <span>Recharge / Payment</span>
          </button>
          <button type="button" class="action-tile bg-[#8cc63f] text-white" @click="activeModal = 'connection'">
            <BadgePercent class="h-10 w-10" />
            <span>New Connection</span>
          </button>
          <button id="complain" type="button" class="action-tile bg-[#0c315f] text-white" @click="activeModal = 'complain'">
            <MessageSquareWarning class="h-10 w-10" />
            <span>Place Complain</span>
          </button>
        </div>
      </section>

      <section class="bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="grid gap-8 lg:grid-cols-[.9fr_1.1fr]">
            <div class="rounded-[2rem] bg-[#0c315f] p-8 text-white">
              <MonitorPlay class="h-12 w-12 text-[#8cc63f]" />
              <h2 class="mt-6 text-4xl font-black uppercase leading-tight">{{ entertainmentSection.title || 'Your ticket to unlimited movies' }}</h2>
              <p class="mt-4 text-sky-100">{{ entertainmentSection.description || 'Bundle entertainment, self-care, support, and payment access in your customer portal.' }}</p>
            </div>
            <div>
              <p class="text-sm font-black uppercase tracking-[0.28em] text-[#8cc63f]">Latest News and Blog</p>
              <div class="mt-6 grid gap-4 md:grid-cols-3">
                <article v-for="item in blogItems" :key="item.slug || item.title" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                  <img v-if="item.image" :src="item.image" :alt="item.title" class="mb-4 h-28 w-full rounded-xl object-cover">
                  <div v-else class="mb-4 h-28 rounded-xl bg-gradient-to-br from-[#0aa4e8] to-[#8cc63f]"></div>
                  <h3 class="font-black uppercase text-[#0c315f]">{{ item.title }}</h3>
                  <p class="mt-3 text-sm leading-6 text-slate-600">{{ item.excerpt }}</p>
                  <a :href="`/blogs/${item.slug}`" class="mt-4 inline-flex text-sm font-black text-[#0aa4e8]">See More</a>
                </article>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer id="contact" class="bg-[#05213f] pt-12 text-white">
      <div class="mx-auto grid max-w-7xl gap-8 px-4 pb-10 sm:px-6 md:grid-cols-4 lg:px-8">
        <div>
          <p class="text-2xl font-black uppercase">{{ tenant.name }}</p>
          <p class="mt-4 text-sm leading-7 text-sky-100">Ultra-high-speed broadband service for homes, students, gamers, offices, and businesses.</p>
        </div>
        <div>
          <p class="font-black uppercase text-[#8cc63f]">Hotline</p>
          <p class="mt-4 text-2xl font-black">{{ tenant.phone }}</p>
          <a :href="`mailto:${tenant.email}`" class="mt-2 block text-sm text-sky-100">{{ tenant.email }}</a>
        </div>
        <div>
          <p class="font-black uppercase text-[#8cc63f]">Address</p>
          <p class="mt-4 text-sm leading-7 text-sky-100">{{ tenant.address }}</p>
          <p v-if="tenant.district" class="mt-2 text-sm text-sky-100">{{ tenant.district }}</p>
        </div>
        <div>
          <p class="font-black uppercase text-[#8cc63f]">Quick Links</p>
          <div class="mt-4 flex flex-col gap-2 text-sm text-sky-100">
            <a href="#packages">Packages</a>
            <button type="button" class="w-fit" @click="activeModal = 'connection'">New Connection</button>
            <button type="button" class="w-fit" @click="activeModal = 'payment'">Payment</button>
            <button type="button" class="w-fit" @click="activeModal = 'complain'">Complain</button>
          </div>
        </div>
      </div>
      <div class="border-t border-white/10 py-5 text-center text-sm text-sky-100">
        {{ tenant.name }} Broadband Internet Service
      </div>
    </footer>

    <div v-if="activeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="activeModal = null">
      <div class="modal-pop w-full max-w-2xl rounded-3xl bg-white p-6 text-slate-950 shadow-2xl">
        <div class="mb-5 flex items-center justify-between">
          <h3 class="text-2xl font-black uppercase text-[#0c315f]">
            <span v-if="activeModal === 'connection'">New Connection Request</span>
            <span v-else-if="activeModal === 'complain'">Place Complain</span>
            <span v-else-if="activeModal === 'payment'">Place Your Transaction ID</span>
            <span v-else>Refer A Friend</span>
          </h3>
          <button type="button" class="rounded-full bg-slate-100 p-2" @click="activeModal = null">
            <X class="h-5 w-5" />
          </button>
        </div>

        <form v-if="activeModal === 'connection'" @submit.prevent="submitConnection">
          <div class="grid gap-4 md:grid-cols-2">
            <input v-model="connectionForm.website" class="hidden" tabindex="-1" autocomplete="off">
            <input v-model="connectionForm.name" class="rounded border-slate-300" placeholder="Your name" required>
            <input v-model="connectionForm.phone" class="rounded border-slate-300" placeholder="Phone number" required>
            <input v-model="connectionForm.address" class="rounded border-slate-300" placeholder="Address">
            <input v-model="connectionForm.area" class="rounded border-slate-300" placeholder="Area / address" required>
            <select v-model="connectionForm.package" class="rounded border-slate-300" required>
              <option value="" disabled>Select package</option>
              <option v-for="item in displayPackages" :key="item.name" :value="item.name">{{ item.name }}</option>
            </select>
            <input v-model="connectionForm.preferred_connection_date" type="date" class="rounded border-slate-300">
            <textarea v-model="connectionForm.message" class="rounded border-slate-300 md:col-span-2" rows="4" placeholder="Message"></textarea>
          </div>
          <button type="submit" class="mt-5 rounded-full bg-[#0aa4e8] px-5 py-3 text-sm font-black uppercase text-white" :disabled="connectionForm.processing">Place Request</button>
          <p v-if="page.props.flash?.success" class="mt-3 text-sm font-bold text-emerald-700">{{ page.props.flash.success }}</p>
        </form>

        <form v-else-if="activeModal === 'complain'" class="grid gap-4 md:grid-cols-2" @submit.prevent="submitComplaint">
          <input v-model="complaintForm.website" class="hidden" tabindex="-1" autocomplete="off">
          <input v-model="complaintForm.customer_id" class="rounded border-slate-300" placeholder="Customer/User ID">
          <input v-model="complaintForm.phone" class="rounded border-slate-300" placeholder="Phone number" required>
          <select v-model="complaintForm.complaint_type" class="rounded border-slate-300" required>
            <option value="" disabled>Complaint type</option>
            <option>Slow Speed</option>
            <option>No Internet</option>
            <option>Billing Issue</option>
            <option>Support Request</option>
          </select>
          <input type="file" class="rounded border border-slate-300 p-2 text-sm" accept="image/*" @change="complaintForm.image = $event.target.files?.[0]">
          <textarea v-model="complaintForm.message" class="rounded border-slate-300 md:col-span-2" rows="4" maxlength="255" placeholder="Write your complain" required></textarea>
          <button type="submit" class="w-fit rounded-full bg-[#0aa4e8] px-5 py-3 text-sm font-black uppercase text-white" :disabled="complaintForm.processing">Submit</button>
        </form>

        <form v-else-if="activeModal === 'referral'" class="grid gap-4 md:grid-cols-2" @submit.prevent="submitReferral">
          <input v-model="referralForm.website" class="hidden" tabindex="-1" autocomplete="off">
          <p class="rounded bg-lime-50 p-3 text-sm font-bold text-[#0c315f] md:col-span-2">{{ frontend.referral_offer }}</p>
          <input v-model="referralForm.friend_name" class="rounded border-slate-300" placeholder="Friend name" required>
          <input v-model="referralForm.friend_phone" class="rounded border-slate-300" placeholder="Friend phone" required>
          <input v-model="referralForm.referrer_user_id" class="rounded border-slate-300 md:col-span-2" placeholder="Your user ID">
          <button type="submit" class="w-fit rounded-full bg-[#0aa4e8] px-5 py-3 text-sm font-black uppercase text-white" :disabled="referralForm.processing">Submit</button>
        </form>

        <form v-else class="grid gap-4 md:grid-cols-2" @submit.prevent="submitPayment">
          <input v-model="paymentForm.website" class="hidden" tabindex="-1" autocomplete="off">
          <input v-model="paymentForm.customer_id" class="rounded border-slate-300" placeholder="Customer/User ID" required>
          <input v-model="paymentForm.amount" type="number" min="1" class="rounded border-slate-300" placeholder="Amount" required>
          <input v-model="paymentForm.transaction_id" class="rounded border-slate-300" placeholder="Transaction ID" required>
          <select v-model="paymentForm.payment_method" class="rounded border-slate-300" required>
            <option value="" disabled>Payment method</option>
            <option v-for="method in frontend.payment_methods" :key="method">{{ method }}</option>
          </select>
          <input type="file" class="rounded border border-slate-300 p-2 text-sm md:col-span-2" accept="image/*" @change="paymentForm.screenshot = $event.target.files?.[0]">
          <button type="submit" class="w-fit rounded-full bg-[#0aa4e8] px-5 py-3 text-sm font-black uppercase text-white" :disabled="paymentForm.processing">Submit</button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hero-slide {
  opacity: 0;
  transform: translateY(28px);
  animation: heroFade 20s infinite;
}

.hero-dot {
  height: 10px;
  width: 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.35);
  animation: dotPulse 20s infinite;
}

.packet {
  position: absolute;
  height: 8px;
  width: 8px;
  border-radius: 999px;
  background: #8cc63f;
  box-shadow: 0 0 22px #8cc63f;
  animation: packetMove 7s linear infinite;
}

.packet-a { left: 8%; top: 22%; animation-delay: 0s; }
.packet-b { left: 18%; top: 76%; animation-delay: 2s; }
.packet-c { left: 76%; top: 18%; animation-delay: 4s; }

.cloud {
  position: absolute;
  height: 80px;
  width: 180px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  filter: blur(1px);
  animation: cloudDrift 18s ease-in-out infinite;
}

.cloud-a { left: 6%; top: 12%; }
.cloud-b { bottom: 10%; right: 12%; animation-delay: 5s; }

.orbit {
  position: absolute;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  animation: spin 18s linear infinite;
}

.orbit-one { inset: 38px 42px 60px 42px; }
.orbit-two { inset: 92px 16px 22px 16px; animation-duration: 24s; animation-direction: reverse; }

.device-mock {
  animation: floatPhone 4s ease-in-out infinite;
}

.speed-badge,
.support-badge,
.booster-card,
.package-card,
.action-tile {
  transition: transform .25s ease, box-shadow .25s ease;
}

.speed-badge { animation: floatPlain 4.4s ease-in-out infinite; }
.support-badge { animation: floatPlain 3.8s ease-in-out infinite reverse; }

.package-card:hover,
.booster-card:hover,
.action-tile:hover {
  transform: translateY(-8px);
  box-shadow: 0 26px 50px rgba(12, 49, 95, 0.16);
}

.action-tile {
  min-height: 190px;
  border-radius: 1.6rem;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 1.5rem;
  font-weight: 900;
  text-transform: uppercase;
}

.gift-shake {
  animation: shakeGift 1.9s ease-in-out infinite;
}

.modal-pop {
  animation: modalPop .22s ease-out;
}

@keyframes heroFade {
  0%, 4% { opacity: 0; transform: translateY(28px); }
  7%, 22% { opacity: 1; transform: translateY(0); }
  25%, 100% { opacity: 0; transform: translateY(-24px); }
}

@keyframes dotPulse {
  0%, 4% { background: rgba(255, 255, 255, 0.35); width: 10px; }
  7%, 22% { background: #8cc63f; width: 34px; }
  25%, 100% { background: rgba(255, 255, 255, 0.35); width: 10px; }
}

@keyframes packetMove {
  from { transform: translate3d(0, 0, 0) scale(.8); opacity: .2; }
  35% { opacity: 1; }
  to { transform: translate3d(460px, -180px, 0) scale(1.3); opacity: 0; }
}

@keyframes cloudDrift {
  0%, 100% { transform: translateX(0); }
  50% { transform: translateX(80px); }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes floatPhone {
  0%, 100% { transform: translate(-50%, 0); }
  50% { transform: translate(-50%, -16px); }
}

@keyframes floatPlain {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-14px); }
}

@keyframes shakeGift {
  0%, 100% { transform: rotate(0); }
  30% { transform: rotate(-8deg); }
  60% { transform: rotate(8deg); }
}

@keyframes modalPop {
  from { opacity: 0; transform: scale(.95) translateY(12px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

@media (max-width: 640px) {
  .device-mock {
    width: 280px;
  }
}
</style>
