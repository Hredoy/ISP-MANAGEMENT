<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2, MapPin, Plus } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ zones: Array });

const form = useForm({ name: '', code: '' });

const submit = () => {
    form.post('/dashboard/zones', { onSuccess: () => form.reset() });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 font-mono">
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-primary font-black tracking-widest uppercase flex items-center gap-2">
                <MapPin :size="18"/> Assigned_Zones
            </h2>
            <div class="border border-primary/20 bg-black overflow-hidden">
                <table class="w-full text-[11px] text-left">
                    <thead class="bg-primary/10 text-primary border-b border-primary/20">
                    <tr>
                        <th class="p-4">CODE</th>
                        <th class="p-4">ZONE_NAME</th>
                        <th class="p-4 text-center">SUB_ZONES</th>
                        <th class="p-4 text-right">ACTION</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                    <tr v-for="zone in zones" :key="zone.id" class="hover:bg-primary/5">
                        <td class="p-4 text-primary font-bold">{{ zone.code || 'N/A' }}</td>
                        <td class="p-4 uppercase">{{ zone.name }}</td>
                        <td class="p-4 text-center">{{ zone.sub_zones_count }}</td>
                        <td class="p-4 text-right">
                            <button @click="$inertia.delete(`/dashboard/zones/${zone.id}`)" class="text-red-500 hover:scale-110">
                                <Trash2 :size="14" />
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border border-primary/30 bg-black/50 p-6 h-fit sticky top-6">
            <h3 class="text-primary font-bold text-xs mb-6 uppercase border-b border-primary/20 pb-2">Initialize_New_Zone</h3>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-[9px] text-primary/60 mb-1 uppercase">Zone_Identifier</label>
                    <input v-model="form.name" type="text" class="w-full bg-black border border-primary/30 p-2 text-primary outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[9px] text-primary/60 mb-1 uppercase">Area_Code</label>
                    <input v-model="form.code" type="text" placeholder="e.g. ZN-01" class="w-full bg-black border border-primary/30 p-2 text-primary outline-none">
                </div>
                <button class="w-full bg-primary text-black font-black py-2 text-[10px] uppercase hover:bg-white transition">
                    Create_Zone_Record
                </button>
            </form>
        </div>
    </div>
</template>
