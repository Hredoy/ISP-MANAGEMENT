<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, Users } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ parentResellers: Array });

const form = useForm({
    name: '',
    phone: '',
    email: '',
    commission_rate: 10,
    parent_reseller_id: '',
    status: 'active',
});
</script>

<template>
    <Head title="ADD_RESELLER" />
    <div class="max-w-2xl mx-auto font-mono text-primary">
        <div class="flex justify-between items-center mb-8 border-b border-primary/20 pb-4">
            <h2 class="text-xl font-black uppercase italic tracking-tighter flex items-center gap-2">
                <Users :size="20" /> Add_Reseller
            </h2>
            <Link :href="route('dashboard.resellers.index')" class="text-[10px] opacity-50 hover:opacity-100 flex items-center gap-1">
                <ChevronLeft :size="14" /> BACK
            </Link>
        </div>

        <form @submit.prevent="form.post(route('dashboard.resellers.store'))" class="space-y-6 bg-surface/40 border border-primary/10 p-8 shadow-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="col-span-2 space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Name *</label>
                    <input v-model="form.name" type="text" placeholder="Reseller name" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none transition">
                    <p v-if="form.errors.name" class="text-red-400 text-[10px]">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Phone</label>
                    <input v-model="form.phone" type="text" placeholder="01XXXXXXXXX" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none transition">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Email</label>
                    <input v-model="form.email" type="email" placeholder="reseller@example.com" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none transition">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Commission Rate (%) *</label>
                    <input v-model="form.commission_rate" type="number" min="0" max="100" step="0.01" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none transition">
                    <p v-if="form.errors.commission_rate" class="text-red-400 text-[10px]">{{ form.errors.commission_rate }}</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Status</label>
                    <select v-model="form.status" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="col-span-2 space-y-1">
                    <label class="text-[10px] uppercase font-bold text-primary/70">Parent Reseller (leave blank for top-level)</label>
                    <select v-model="form.parent_reseller_id" class="w-full bg-surface border border-primary/30 p-3 text-sm focus:border-primary outline-none">
                        <option value="">— Independent / Direct under ISP —</option>
                        <option v-for="r in parentResellers" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" :disabled="form.processing" class="w-full bg-primary text-black font-black py-4 uppercase text-xs hover:bg-white transition flex items-center justify-center gap-2">
                    <Save :size="16" /> {{ form.processing ? 'SAVING...' : 'Add Reseller' }}
                </button>
            </div>
        </form>
    </div>
</template>
