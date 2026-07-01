<script setup>
import LandlordLayout from '@/Layouts/LandlordLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  tenants: { type: Array, default: () => [] },
});

const statItems = computed(() => [
  ['Total tenants', props.stats.total_tenants ?? 0],
  ['Active tenants', props.stats.active_tenants ?? 0],
  ['Pending applications', props.stats.pending_applications ?? 0],
  ['Rejected applications', props.stats.rejected_applications ?? 0],
  ['Suspended tenants', props.stats.suspended_tenants ?? 0],
  ['Pending setup', props.stats.pending_setup_tenants ?? 0],
]);
</script>

<template>
  <Head title="Landlord Dashboard" />
  <LandlordLayout>
    <div class="space-y-6">
      <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div v-for="[label, value] in statItems" :key="label" class="rounded border border-gray-200 bg-white p-4">
          <p class="text-xs font-semibold uppercase text-gray-500">{{ label }}</p>
          <p class="mt-2 text-2xl font-black text-gray-950">{{ value }}</p>
        </div>
      </div>

      <section class="overflow-hidden rounded border border-gray-200 bg-white">
        <div class="border-b border-gray-200 p-5">
          <h3 class="font-bold text-gray-950">Tenant database and domain status</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr>
                <th class="px-4 py-3">Tenant</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Database</th>
                <th class="px-4 py-3">Domain</th>
                <th class="px-4 py-3">Enabled modules</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tenant in tenants" :key="tenant.id" class="border-t border-gray-100">
                <td class="px-4 py-3 font-bold text-gray-950">{{ tenant.organization_name || tenant.id }}</td>
                <td class="px-4 py-3">{{ tenant.status }}</td>
                <td class="px-4 py-3">{{ tenant.database_status }} · {{ tenant.database_name || '-' }}</td>
                <td class="px-4 py-3">{{ tenant.domain_status }}</td>
                <td class="px-4 py-3">
                  {{ tenant.modules?.filter((item) => item.enabled).map((item) => item.module?.name).join(', ') || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </LandlordLayout>
</template>
