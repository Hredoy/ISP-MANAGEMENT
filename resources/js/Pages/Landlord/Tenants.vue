<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  applications: {
    type: Array,
    default: () => [],
  },
});

const approve = (id) => {
  router.post(route('landlord.tenants.approve', id));
};
</script>

<template>
  <Head title="Landlord Tenants" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Landlord Tenant Approvals</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow sm:rounded-lg p-6 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left border-b">
                <th class="py-2">Organization</th>
                <th class="py-2">Contact</th>
                <th class="py-2">Status</th>
                <th class="py-2">Database</th>
                <th class="py-2">Subdomain</th>
                <th class="py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tenant in applications" :key="tenant.id" class="border-b">
                <td class="py-2">{{ tenant.organization_name }}</td>
                <td class="py-2">{{ tenant.contact_name }} ({{ tenant.email }})</td>
                <td class="py-2 uppercase">{{ tenant.status }}</td>
                <td class="py-2">{{ tenant.database_name || '-' }}</td>
                <td class="py-2">{{ tenant.subdomain || '-' }}</td>
                <td class="py-2">
                  <button
                    v-if="tenant.status === 'pending'"
                    @click="approve(tenant.id)"
                    class="px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                  >
                    Approve
                  </button>
                  <span v-else class="text-gray-500">No action</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
