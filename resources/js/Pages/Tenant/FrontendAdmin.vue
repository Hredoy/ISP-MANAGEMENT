<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
  BadgeDollarSign,
  BellDot,
  Gift,
  Globe2,
  MessageSquareWarning,
  Save,
  Search,
  Settings2,
  UserPlus,
} from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: ISPLayout });

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  connections: { type: Array, default: () => [] },
  complaints: { type: Array, default: () => [] },
  referrals: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  blogs: { type: Array, default: () => [] },
});

const page = usePage();
const form = useForm({
  site_name: props.settings.site_name || '',
  logo: props.settings.logo || '',
  favicon: props.settings.favicon || '',
  phone: props.settings.phone || '',
  email: props.settings.email || '',
  address: props.settings.address || '',
  about: props.settings.about || '',
  primary_color: props.settings.primary_color || '#0aa4e8',
  accent_color: props.settings.accent_color || '#8cc63f',
  seo_title: props.settings.seo_title || '',
  seo_description: props.settings.seo_description || '',
  seo_keywords: props.settings.seo_keywords || '',
  referral_offer: props.settings.referral_offer || '',
  footer_about: props.settings.footer_about || '',
  terms: props.settings.terms || '',
  privacy: props.settings.privacy || '',
  refund: props.settings.refund || '',
});

const stats = computed(() => [
  { label: 'Connection_Requests', value: props.connections.length, icon: UserPlus },
  { label: 'Complaints', value: props.complaints.length, icon: MessageSquareWarning },
  { label: 'Referrals', value: props.referrals.length, icon: Gift },
  { label: 'Manual_Payments', value: props.payments.length, icon: BadgeDollarSign },
]);

const submit = () => {
  form.patch(route('dashboard.frontend.update'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Tenant_Frontend" />

  <div class="space-y-6 font-mono text-primary">
    <div class="flex flex-col justify-between gap-4 border-b border-primary/20 pb-6 md:flex-row md:items-center">
      <div>
        <h1 class="flex items-center gap-2 text-2xl font-black uppercase italic tracking-tighter">
          <Globe2 :size="24" /> Tenant_Frontend
        </h1>
        <p class="mt-2 text-[10px] uppercase tracking-widest text-primary/60">
          Website branding, SEO, public forms, and customer submissions.
        </p>
      </div>
      <a href="/" target="_blank" class="w-fit border border-primary/40 px-4 py-2 text-[10px] font-black uppercase hover:bg-primary hover:text-black">
        Open_Website
      </a>
    </div>

    <div v-if="page.props.flash?.success" class="border border-primary/30 bg-primary/10 p-4 text-[11px] font-black uppercase">
      {{ page.props.flash.success }}
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="item in stats" :key="item.label" class="border border-primary/20 bg-black p-4">
        <div class="flex items-center justify-between">
          <p class="text-[9px] uppercase text-primary/50">{{ item.label }}</p>
          <component :is="item.icon" :size="18" class="text-primary/40" />
        </div>
        <p class="mt-3 text-3xl font-black text-white">{{ item.value }}</p>
      </div>
    </div>

    <form class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]" @submit.prevent="submit">
      <section class="space-y-5 border border-primary/20 bg-black p-5">
        <h2 class="flex items-center gap-2 text-sm font-black uppercase text-white">
          <Settings2 :size="18" /> Brand_Settings
        </h2>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Site_Name
            <input v-model="form.site_name" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Phone
            <input v-model="form.phone" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Email
            <input v-model="form.email" type="email" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Logo_URL
            <input v-model="form.logo" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Primary_Color
            <input v-model="form.primary_color" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60">
            Accent_Color
            <input v-model="form.accent_color" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60 md:col-span-2">
            Address
            <textarea v-model="form.address" rows="2" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary"></textarea>
          </label>
          <label class="text-[10px] font-bold uppercase text-primary/60 md:col-span-2">
            About
            <textarea v-model="form.about" rows="4" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary"></textarea>
          </label>
        </div>
      </section>

      <section class="space-y-5 border border-primary/20 bg-black p-5">
        <h2 class="flex items-center gap-2 text-sm font-black uppercase text-white">
          <Search :size="18" /> SEO_And_Copy
        </h2>

        <label class="block text-[10px] font-bold uppercase text-primary/60">
          SEO_Title
          <input v-model="form.seo_title" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary" />
        </label>
        <label class="block text-[10px] font-bold uppercase text-primary/60">
          SEO_Description
          <textarea v-model="form.seo_description" rows="3" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary"></textarea>
        </label>
        <label class="block text-[10px] font-bold uppercase text-primary/60">
          SEO_Keywords
          <textarea v-model="form.seo_keywords" rows="2" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary"></textarea>
        </label>
        <label class="block text-[10px] font-bold uppercase text-primary/60">
          Referral_Offer
          <textarea v-model="form.referral_offer" rows="3" class="mt-2 w-full border-primary/30 bg-black text-primary focus:border-primary focus:ring-primary"></textarea>
        </label>
        <button type="submit" class="inline-flex items-center gap-2 bg-primary px-5 py-3 text-[10px] font-black uppercase text-black hover:bg-white" :disabled="form.processing">
          <Save :size="16" /> Save_Settings
        </button>
      </section>
    </form>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white">
          <UserPlus :size="18" /> Latest_Connections
        </h2>
        <div class="space-y-3">
          <div v-for="item in connections" :key="item.id" class="border border-primary/10 p-3 text-[10px]">
            <div class="flex justify-between gap-3 text-white">
              <span class="font-black uppercase">{{ item.name }}</span>
              <span>{{ item.phone }}</span>
            </div>
            <p class="mt-2 text-primary/60">{{ item.area || item.address || 'Area not provided' }} / {{ item.package || 'Package not selected' }}</p>
          </div>
          <p v-if="connections.length === 0" class="text-[10px] uppercase text-primary/50">No connection requests yet.</p>
        </div>
      </section>

      <section class="border border-primary/20 bg-black p-5">
        <h2 class="mb-4 flex items-center gap-2 text-sm font-black uppercase text-white">
          <BellDot :size="18" /> Latest_Public_Forms
        </h2>
        <div class="space-y-3">
          <div v-for="item in complaints" :key="`c-${item.id}`" class="border border-primary/10 p-3 text-[10px]">
            <div class="flex justify-between gap-3 text-white">
              <span class="font-black uppercase">{{ item.complaint_type }}</span>
              <span>{{ item.phone }}</span>
            </div>
            <p class="mt-2 text-primary/60">{{ item.message }}</p>
          </div>
          <div v-for="item in payments" :key="`p-${item.id}`" class="border border-primary/10 p-3 text-[10px]">
            <div class="flex justify-between gap-3 text-white">
              <span class="font-black uppercase">{{ item.payment_method }}</span>
              <span>{{ item.amount }}</span>
            </div>
            <p class="mt-2 text-primary/60">TXID: {{ item.transaction_id }} / Customer: {{ item.customer_id }}</p>
          </div>
          <div v-for="item in referrals" :key="`r-${item.id}`" class="border border-primary/10 p-3 text-[10px]">
            <div class="flex justify-between gap-3 text-white">
              <span class="font-black uppercase">{{ item.friend_name }}</span>
              <span>{{ item.friend_phone }}</span>
            </div>
            <p class="mt-2 text-primary/60">Referrer: {{ item.referrer_user_id || 'Not provided' }}</p>
          </div>
          <p v-if="complaints.length + payments.length + referrals.length === 0" class="text-[10px] uppercase text-primary/50">No complaint, payment, or referral submissions yet.</p>
        </div>
      </section>
    </div>
  </div>
</template>
