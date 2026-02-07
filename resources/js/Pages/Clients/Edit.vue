<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, RefreshCw, AlertTriangle } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({
    client: Object,
    routers: Array,
    zones: Array,
    subZones: Array,
    packages: Array
});

const form = useForm({
    ...props.client // Automatically fills form with existing client data
});

const generatePassword = () => {
    form.pppoe_password = Math.random().toString(36).slice(-8);
};

const submit = () => {
    form.put(route('dashboard.clients.update', props.client.id));
};
</script>

<template>
    <Head :title="'EDIT_CLIENT_' + client.pppoe_username" />
    <div class="max-w-5xl mx-auto font-mono text-primary">

        <div class="flex justify-between items-center mb-6">
            <Link :href="route('dashboard.clients.index')" class="flex items-center gap-2 text-[10px] uppercase opacity-60 hover:opacity-100 transition">
                <ChevronLeft :size="14" /> ABORT_EDIT
            </Link>
            <div class="text-right">
                <h2 class="text-xl font-black uppercase italic tracking-tighter text-white">Modify_Subscriber_Node</h2>
                <p class="text-[9px] text-yellow-500 flex items-center justify-end gap-1">
                    <AlertTriangle :size="10" /> CHANGES_WILL_SYNC_TO_MIKROTIK
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-8 pb-20">
            <div class="border border-primary/20 bg-black/60 p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Assign Router</label>
                        <select v-model="form.mikrotik_id" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option v-for="r in routers" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Active Package</label>
                        <select v-model="form.package_name" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none text-white">
                            <option v-for="p in packages" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Current Status</label>
                        <select v-model="form.status" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none" :class="form.status === 'Active' ? 'text-primary' : 'text-red-500'">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-primary/10">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PPPoE Username</label>
                        <input v-model="form.pppoe_username" type="text" class="w-full bg-black border border-primary/30 p-2 text-xs text-white/50 cursor-not-allowed" readonly>
                        <p class="text-[7px] text-yellow-500 uppercase mt-1 italic">!! Username changes require MikroTik manual check</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Update Password</label>
                        <div class="flex gap-2">
                            <input v-model="form.pppoe_password" type="text" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                            <button type="button" @click="generatePassword" class="bg-primary/10 border border-primary/30 px-3 text-[10px] hover:bg-primary hover:text-black transition">
                                <RefreshCw :size="12" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-primary/20 bg-black/60 p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Full Name</label>
                        <input v-model="form.full_name" type="text" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Expiry Date</label>
                        <input v-model="form.expiry_date" type="date" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button :disabled="form.processing" type="submit" class="bg-primary text-black font-black px-10 py-4 uppercase text-xs flex items-center gap-2 hover:bg-white transition">
                    <Save :size="18" /> {{ form.processing ? 'SYNCING...' : 'COMMIT_CHANGES' }}
                </button>
            </div>
        </form>
    </div>
</template>
