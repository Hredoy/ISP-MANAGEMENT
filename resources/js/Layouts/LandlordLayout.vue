<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import { Building2, FileText, LayoutDashboard, Users } from 'lucide-vue-next';

const links = [
  { label: 'Dashboard', routeName: 'landlord.dashboard', icon: LayoutDashboard },
  { label: 'Applications', routeName: 'landlord.applications.index', icon: FileText },
  { label: 'Tenants', routeName: 'landlord.tenants.index', icon: Users },
];
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-950 text-white">
            <Building2 class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Landlord Management</h2>
            <p class="text-sm text-gray-500">Tenant applications, provisioning, modules, and status controls</p>
          </div>
        </div>
        <a href="/apply-organization" class="rounded bg-gray-950 px-3 py-2 text-sm font-semibold text-white">
          Public application
        </a>
      </div>
    </template>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:px-8">
      <aside class="hidden w-64 shrink-0 md:block">
        <nav class="sticky top-6 rounded border border-gray-200 bg-white p-2">
          <Link
            v-for="item in links"
            :key="item.routeName"
            :href="route(item.routeName)"
            class="mb-1 flex items-center gap-3 rounded px-3 py-2 text-sm font-semibold"
            :class="route().current(item.routeName) ? 'bg-gray-950 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-950'"
          >
            <component :is="item.icon" class="h-4 w-4" />
            {{ item.label }}
          </Link>
        </nav>
      </aside>

      <main class="min-w-0 flex-1">
        <div class="mb-4 flex gap-2 overflow-x-auto md:hidden">
          <Link
            v-for="item in links"
            :key="item.routeName"
            :href="route(item.routeName)"
            class="shrink-0 rounded border px-3 py-2 text-sm font-semibold"
            :class="route().current(item.routeName) ? 'border-gray-950 bg-gray-950 text-white' : 'border-gray-200 bg-white text-gray-700'"
          >
            {{ item.label }}
          </Link>
        </div>

        <slot />
      </main>
    </div>
  </AuthenticatedLayout>
</template>
