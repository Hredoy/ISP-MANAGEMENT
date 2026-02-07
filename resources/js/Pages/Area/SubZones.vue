<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2, MapPin, Plus } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ subZones: Array, zones: Array });

const form = useForm({ name: '', zone_id: '', manager_contact: '' });

const submit = () => {
    form.post('/dashboard/sub-zones', { onSuccess: () => form.reset() });
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
                        <th class="p-4">SUB_ZONE_NAME</th>
                        <th class="p-4">ZONE_NAME</th>
                        <th class="p-4 text-center">MANAGER_CONTACT</th>
                        <th class="p-4 text-right">ACTION</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                    <tr v-for="subZone in subZones" :key="subZone.id" class="hover:bg-primary/5">
                        <td class="p-4 uppercase">{{ subZone.name }}</td>
                        <td class="p-4 uppercase">{{ subZone.zone.name }}</td>
                        <td class="p-4 text-center">{{ subZone.manager_contact }}</td>
                        <td class="p-4 text-right">
                            <button @click="$inertia.delete(`/dashboard/sub-zones/${subZone.id}`)" class="text-red-500 hover:scale-110">
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
                    <label class="block text-[9px] text-primary/60 mb-1 uppercase">Sub_Zone_Identifier</label>
                    <input v-model="form.name" type="text" class="w-full bg-black border border-primary/30 p-2 text-primary outline-none focus:border-primary">
                </div>
                <label class="block text-[9px] text-primary/60 mb-1 uppercase">Parent_Zone</label>
                <select v-model="form.zone_id" class="w-full bg-black border border-primary/30 p-2 text-primary outline-none">
                    <option v-for="zone in zones" :key="zone.id" :value="zone.id">
                        {{ zone.name }}
                    </option>
                </select>
                <div>
                    <label class="block text-[9px] text-primary/60 mb-1 uppercase">Manager_Contact</label>
                    <input v-model="form.manager_contact" type="text" placeholder="e.g. 01XXXXXXXXXX" class="w-full bg-black border border-primary/30 p-2 text-primary outline-none">
                </div>
                <button class="w-full bg-primary text-black font-black py-2 text-[10px] uppercase hover:bg-white transition">
                    Create_Zone_Record
                </button>
            </form>
        </div>
    </div>
</template>
