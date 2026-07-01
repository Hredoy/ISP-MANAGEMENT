<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Send } from 'lucide-vue-next';

defineProps({
  modules: { type: Array, default: () => [] },
});

const form = useForm({
  organization_name: '',
  owner_name: '',
  email: '',
  phone: '',
  address: '',
  domain_request: '',
  business_type: '',
  package_request: 'starter',
  module_request: [],
  notes: '',
});

const submit = () => {
  form.post(route('tenant.apply.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Organization Application" />

    <main class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
      <div class="mb-6 flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded bg-gray-950 text-white">
          <Building2 class="h-5 w-5" />
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-950">Organization Application</h1>
          <p class="text-sm text-gray-600">Apply for an ISP Management tenant workspace.</p>
        </div>
      </div>

      <form class="bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-6" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Organization name</span>
            <input v-model="form.organization_name" class="mt-1 w-full rounded border-gray-300" type="text" required>
            <p class="mt-1 text-xs text-red-600">{{ form.errors.organization_name }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Owner name</span>
            <input v-model="form.owner_name" class="mt-1 w-full rounded border-gray-300" type="text" required>
            <p class="mt-1 text-xs text-red-600">{{ form.errors.owner_name }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Email</span>
            <input v-model="form.email" class="mt-1 w-full rounded border-gray-300" type="email" required>
            <p class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Phone</span>
            <input v-model="form.phone" class="mt-1 w-full rounded border-gray-300" type="tel" required>
            <p class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
          </label>
          <label class="block md:col-span-2">
            <span class="text-sm font-semibold text-gray-800">Address</span>
            <input v-model="form.address" class="mt-1 w-full rounded border-gray-300" type="text">
            <p class="mt-1 text-xs text-red-600">{{ form.errors.address }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Domain/subdomain request</span>
            <input v-model="form.domain_request" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="example or example.com">
            <p class="mt-1 text-xs text-red-600">{{ form.errors.domain_request }}</p>
          </label>
          <label class="block">
            <span class="text-sm font-semibold text-gray-800">Business type</span>
            <input v-model="form.business_type" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="ISP, cable, broadband">
            <p class="mt-1 text-xs text-red-600">{{ form.errors.business_type }}</p>
          </label>
          <label class="block md:col-span-2">
            <span class="text-sm font-semibold text-gray-800">Package/module request</span>
            <input v-model="form.package_request" class="mt-1 w-full rounded border-gray-300" type="text" placeholder="starter, pro, enterprise">
            <p class="mt-1 text-xs text-red-600">{{ form.errors.package_request }}</p>
          </label>
        </div>

        <div class="mt-5">
          <p class="text-sm font-semibold text-gray-800">Requested modules</p>
          <div class="mt-2 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            <label v-for="module in modules" :key="module.slug" class="inline-flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm">
              <input v-model="form.module_request" type="checkbox" :value="module.slug" class="rounded border-gray-300">
              {{ module.name }}
            </label>
          </div>
          <p class="mt-1 text-xs text-red-600">{{ form.errors.module_request }}</p>
        </div>

        <label class="mt-5 block">
          <span class="text-sm font-semibold text-gray-800">Notes/message</span>
          <textarea v-model="form.notes" class="mt-1 w-full rounded border-gray-300" rows="4"></textarea>
          <p class="mt-1 text-xs text-red-600">{{ form.errors.notes }}</p>
        </label>

        <div class="mt-6 flex items-center gap-3">
          <button type="submit" class="inline-flex items-center gap-2 rounded bg-indigo-600 px-4 py-2 font-semibold text-white disabled:opacity-60" :disabled="form.processing">
            <Send class="h-4 w-4" />
            {{ form.processing ? 'Submitting...' : 'Submit application' }}
          </button>
          <p v-if="$page.props.flash.success" class="text-sm font-semibold text-emerald-700">{{ $page.props.flash.success }}</p>
        </div>
      </form>
    </main>
  </GuestLayout>
</template>
