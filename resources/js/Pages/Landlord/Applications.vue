<script setup>
import LandlordLayout from '@/Layouts/LandlordLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Database, X } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
  applications: { type: Array, default: () => [] },
  modules: { type: Array, default: () => [] },
});

const rejectReasons = ref({});
const manualForm = useForm({
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

const submitManual = () => {
  manualForm.post(route('landlord.applications.store'), {
    preserveScroll: true,
    onSuccess: () => manualForm.reset(),
  });
};

const approve = (id) => router.post(route('landlord.tenants.approve', id), {}, { preserveScroll: true });
const convert = (id) => router.post(route('landlord.tenants.convert', id), {}, { preserveScroll: true });
const reject = (id) => router.post(route('landlord.tenants.reject', id), { reason: rejectReasons.value[id] }, { preserveScroll: true });
</script>

<template>
  <Head title="Organization Applications" />
  <LandlordLayout>
    <div class="space-y-6">
      <form class="rounded border border-gray-200 bg-white p-5" @submit.prevent="submitManual">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-bold text-gray-950">Create application manually</h3>
          <button type="submit" class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white" :disabled="manualForm.processing">
            Save application
          </button>
        </div>
        <div class="grid gap-3 md:grid-cols-3">
          <input v-model="manualForm.organization_name" class="rounded border-gray-300" placeholder="Organization name" required>
          <input v-model="manualForm.owner_name" class="rounded border-gray-300" placeholder="Owner name" required>
          <input v-model="manualForm.email" class="rounded border-gray-300" type="email" placeholder="Email" required>
          <input v-model="manualForm.phone" class="rounded border-gray-300" placeholder="Phone" required>
          <input v-model="manualForm.domain_request" class="rounded border-gray-300" placeholder="Domain or subdomain">
          <input v-model="manualForm.business_type" class="rounded border-gray-300" placeholder="Business type">
          <input v-model="manualForm.package_request" class="rounded border-gray-300" placeholder="Package request">
          <input v-model="manualForm.address" class="rounded border-gray-300 md:col-span-2" placeholder="Address">
          <textarea v-model="manualForm.notes" class="rounded border-gray-300 md:col-span-3" rows="2" placeholder="Notes"></textarea>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <label v-for="module in modules" :key="module.slug" class="inline-flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm">
            <input v-model="manualForm.module_request" type="checkbox" :value="module.slug" class="rounded border-gray-300">
            {{ module.name }}
          </label>
        </div>
      </form>

      <section class="overflow-hidden rounded border border-gray-200 bg-white">
        <div class="border-b border-gray-200 p-5">
          <h3 class="font-bold text-gray-950">Organization applications</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr>
                <th class="px-4 py-3">Organization</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3">Request</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Provisioning</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="application in applications" :key="application.id" class="border-t border-gray-100 align-top">
                <td class="px-4 py-3">
                  <p class="font-bold text-gray-950">{{ application.organization_name }}</p>
                  <p class="text-xs text-gray-500">{{ application.business_type || 'Business type pending' }}</p>
                </td>
                <td class="px-4 py-3">
                  <p>{{ application.owner_name || application.contact_name }}</p>
                  <p class="text-xs text-gray-500">{{ application.email }} · {{ application.phone }}</p>
                </td>
                <td class="px-4 py-3">
                  <p>{{ application.package_request || application.plan || 'starter' }}</p>
                  <p class="text-xs text-gray-500">{{ application.domain_request || application.custom_domain || 'No domain request' }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded bg-gray-100 px-2 py-1 text-xs font-bold uppercase">{{ application.status }}</span>
                  <p v-if="application.rejection_reason" class="mt-2 text-xs text-red-600">{{ application.rejection_reason }}</p>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                  <p>DB: {{ application.database_name || '-' }}</p>
                  <p>Domain: {{ application.subdomain || '-' }}</p>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <button v-if="application.status === 'pending'" class="inline-flex items-center gap-1 rounded bg-emerald-600 px-2 py-1 font-semibold text-white" @click="approve(application.id)">
                      <Check class="h-3 w-3" /> Approve
                    </button>
                    <button v-if="application.status === 'approved'" class="inline-flex items-center gap-1 rounded bg-indigo-600 px-2 py-1 font-semibold text-white" @click="convert(application.id)">
                      <Database class="h-3 w-3" /> Convert
                    </button>
                  </div>
                  <div v-if="application.status !== 'converted'" class="mt-2 flex gap-2">
                    <input v-model="rejectReasons[application.id]" class="w-44 rounded border-gray-300 text-xs" placeholder="Reject reason">
                    <button class="inline-flex items-center gap-1 rounded bg-red-600 px-2 py-1 font-semibold text-white" @click="reject(application.id)">
                      <X class="h-3 w-3" /> Reject
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </LandlordLayout>
</template>
