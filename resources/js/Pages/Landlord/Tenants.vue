<script setup>
import LandlordLayout from '@/Layouts/LandlordLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ShieldOff, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
  tenants: { type: Array, default: () => [] },
  modules: { type: Array, default: () => [] },
});

const statusMessages = ref({});
const enabledSlugs = (tenant) => tenant.modules?.filter((item) => item.enabled).map((item) => item.module?.slug) ?? [];
const tenantHasModule = (tenant, slug) => enabledSlugs(tenant).includes(slug);

const toggleModule = (tenant, slug) => {
  const next = tenantHasModule(tenant, slug)
    ? enabledSlugs(tenant).filter((item) => item !== slug)
    : [...enabledSlugs(tenant), slug];

  router.patch(route('landlord.tenants.modules.update', tenant.id), { modules: next }, { preserveScroll: true });
};

const updateStatus = (tenant, status) => {
  router.patch(route('landlord.tenants.status.update', tenant.id), {
    status,
    message: statusMessages.value[tenant.id] ?? '',
  }, { preserveScroll: true });
};
</script>

<template>
  <Head title="Tenant Controls" />
  <LandlordLayout>
    <section class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-gray-950">Tenant controls</h3>
        <p class="text-sm text-gray-500">{{ tenants.length }} tenants</p>
      </div>

      <div v-for="tenant in tenants" :key="tenant.id" class="rounded border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h4 class="text-lg font-black text-gray-950">{{ tenant.organization_name || tenant.id }}</h4>
            <p class="text-sm text-gray-500">{{ tenant.admin_email }} · {{ tenant.status }}</p>
            <p class="mt-1 text-xs text-gray-500">DB {{ tenant.database_name || '-' }}: {{ tenant.database_status }} · Domain: {{ tenant.domain_status }}</p>
            <p v-if="tenant.ssl_status && tenant.ssl_status !== 'not_applicable'" class="mt-1 text-xs text-gray-500">Custom domain SSL: {{ tenant.ssl_status }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="rounded border px-3 py-2 text-sm font-semibold" @click="updateStatus(tenant, 'active')">Activate</button>
            <button class="rounded border px-3 py-2 text-sm font-semibold" @click="updateStatus(tenant, 'inactive')">Deactivate</button>
            <button class="inline-flex items-center gap-2 rounded bg-red-600 px-3 py-2 text-sm font-semibold text-white" @click="updateStatus(tenant, 'suspended')">
              <ShieldOff class="h-4 w-4" /> Suspend
            </button>
          </div>
        </div>

        <input v-model="statusMessages[tenant.id]" class="mt-3 w-full rounded border-gray-300 text-sm" placeholder="Suspension message">

        <div class="mt-4 grid gap-2 md:grid-cols-4">
          <button v-for="module in modules" :key="module.slug" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 text-sm font-semibold" @click="toggleModule(tenant, module.slug)">
            <span>{{ module.name }}</span>
            <ToggleRight v-if="tenantHasModule(tenant, module.slug)" class="h-5 w-5 text-emerald-600" />
            <ToggleLeft v-else class="h-5 w-5 text-gray-400" />
          </button>
        </div>

        <div v-if="tenant.provisioning_logs?.length" class="mt-4 grid gap-2 text-xs md:grid-cols-4">
          <div v-for="log in tenant.provisioning_logs" :key="log.id" class="rounded bg-gray-50 p-2">
            <p class="font-bold uppercase text-gray-600">{{ log.step }} · {{ log.status }}</p>
            <p class="mt-1 text-gray-500">{{ log.message }}</p>
          </div>
        </div>
      </div>
    </section>
  </LandlordLayout>
</template>
