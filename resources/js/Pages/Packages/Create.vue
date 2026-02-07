<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, Zap } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ routers: Array });

const form = useForm({
    name: '', mikrotik_id: '', rate_limit: '10M/10M',
    price: 0, local_address: '', remote_address: '', description: ''
});
</script>

<template>
    <Head title="CREATE_NEW_PACKAGE" />
    <div class="max-w-3xl mx-auto font-mono text-primary">
        <div class="flex justify-between items-center mb-8 border-b border-primary/20 pb-4">
            <h2 class="text-xl font-black uppercase italic tracking-tighter flex items-center gap-2">
                <Zap :size="20" /> Create_New_Package
            </h2>
            <Link :href="route('dashboard.packages.index')" class="text-[10px] opacity-50 hover:opacity-100 flex items-center gap-1">
                <ChevronLeft :size="14" /> BACK_TO_LIST
            </Link>
        </div>

        <form @submit.prevent="form.post(route('dashboard.packages.store'))" class="space-y-6 bg-black/40 border border-primary/10 p-8 shadow-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-2 space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Package Name *</label>
                    <input v-model="form.name" type="text" placeholder="e.g. 10MBPS Unlimited" class="w-full bg-black border border-primary/30 p-3 text-sm focus:border-primary outline-none transition">
                </div>

                <div class="col-span-2 space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Select Router *</label>
                    <select v-model="form.mikrotik_id" class="w-full bg-black border border-primary/30 p-3 text-sm focus:border-primary outline-none">
                        <option value="">-- Choose Router --</option>
                        <option v-for="r in routers" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Rate Limit *</label>
                    <input v-model="form.rate_limit" type="text" placeholder="10M/10M" class="w-full bg-black border border-primary/30 p-3 text-sm outline-none">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Price (BDT)</label>
                    <input v-model="form.price" type="number" step="0.01" class="w-full bg-black border border-primary/30 p-3 text-sm outline-none text-white font-bold">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Local Address</label>
                    <input v-model="form.local_address" type="text" placeholder="Ip Address" class="w-full bg-black border border-primary/30 p-3 text-sm outline-none">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Remote Address</label>
                    <input v-model="form.remote_address" type="text" placeholder="Ip Address/Pool" class="w-full bg-black border border-primary/30 p-3 text-sm outline-none">
                </div>

                <div class="col-span-2 space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Description</label>
                    <textarea v-model="form.description" rows="3" class="w-full bg-black border border-primary/30 p-3 text-sm outline-none"></textarea>
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" :disabled="form.processing" class="w-full bg-primary text-black font-black py-4 uppercase text-xs hover:bg-white transition flex items-center justify-center gap-2">
                    <Save :size="16" /> {{ form.processing ? 'SYNCING_TO_ROUTER...' : 'Create Package' }}
                </button>
            </div>
        </form>
    </div>
</template>
