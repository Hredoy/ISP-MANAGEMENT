<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

const props = defineProps({
  roles: Array,
  permissions: Object,
  disabledPermissionNames: Array,
});

const page = usePage();
const forms = {};

props.roles.forEach((role) => {
  forms[role.id] = useForm({
    permissions: role.permissions.map((permission) => permission.name),
  });
});

const save = (role) => {
  forms[role.id].patch(route('dashboard.roles-permissions.roles.update', role.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Role_Permissions" />

  <div class="space-y-6 font-mono text-primary">
    <div class="border-b border-primary/20 pb-6">
      <h1 class="flex items-center gap-2 text-2xl font-black uppercase italic tracking-tighter">
        <ShieldCheck :size="24" /> Tenant_Role_Permissions
      </h1>
      <p class="mt-2 text-[10px] uppercase tracking-widest text-primary/60">
        Permissions shown here are filtered by landlord-enabled tenant modules.
      </p>
    </div>

    <div v-if="page.props.flash?.success" class="border border-primary/30 bg-primary/10 p-4 text-[11px] font-black uppercase">
      {{ page.props.flash.success }}
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <section v-for="role in roles" :key="role.id" class="border border-primary/20 bg-black p-5">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-sm font-black uppercase text-white">{{ role.name }}</h2>
          <button class="inline-flex items-center gap-2 bg-primary px-4 py-2 text-[10px] font-black uppercase text-black" @click="save(role)">
            <Save :size="14" /> Save
          </button>
        </div>

        <div class="space-y-5">
          <div v-for="(items, module) in permissions" :key="module">
            <p class="mb-2 text-[10px] font-black uppercase text-primary/50">{{ module }}</p>
            <div class="grid gap-2 sm:grid-cols-2">
              <label v-for="permission in items" :key="permission.id" class="flex items-center gap-2 border border-primary/10 p-2 text-[10px] uppercase">
                <input v-model="forms[role.id].permissions" type="checkbox" :value="permission.name" class="border-primary/30 bg-black text-primary">
                {{ permission.name }}
              </label>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section v-if="disabledPermissionNames.length" class="border border-yellow-500/30 bg-yellow-500/10 p-5">
      <h2 class="text-sm font-black uppercase text-yellow-300">Hidden_By_Landlord_Module_Control</h2>
      <p class="mt-3 text-[10px] leading-6 text-yellow-100">{{ disabledPermissionNames.join(', ') }}</p>
    </section>
  </div>
</template>
