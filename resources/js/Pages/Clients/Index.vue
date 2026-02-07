<script setup>
import { ref, computed } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, Search, UserPlus, Trash2, Edit3, Eye, ExternalLink } from 'lucide-vue-next';
import {createToaster} from "@meforma/vue-toaster";

const toaster = createToaster();
defineOptions({ layout: ISPLayout });
const props = defineProps({ clients: Array });

const search = ref('');
const filteredClients = computed(() => {
    return props.clients.filter(c =>
        c.full_name.toLowerCase().includes(search.value.toLowerCase()) ||
        c.pppoe_username.toLowerCase().includes(search.value.toLowerCase())
    );
});

const deleteClient = (id, name) => {
    if (confirm(`CRITICAL_ACTION: Are you sure you want to terminate ${name}? This will remove them from the Router and Database.`)) {
        router.delete(route('dashboard.clients.destroy', id), {
            onBefore: () => {
                // You could trigger a loading state here
                console.log("INITIATING_TERMINATION_PROTOCOL...");
            },
            onSuccess: () => {
                toaster.info(">> CLIENT_TERMINATED_AND_ROUTER_CLEANED");
            }
        });
    }
};
</script>

<template>
    <Head title="CLIENT_DIRECTORY" />
    <div class="space-y-6 font-mono text-primary">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-primary/20 pb-6">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                    <Users :size="24" /> Subscriber_Database
                </h1>
                <p class="text-[9px] opacity-50">AUTHORIZED_ACCESS_ONLY // TOTAL_NODES: {{ clients.length }}</p>
            </div>

            <div class="flex gap-4 w-full md:w-auto">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 opacity-50" :size="14" />
                    <input v-model="search" type="text" placeholder="SEARCH_BY_USER_OR_PPPOE..."
                           class="w-full md:w-64 bg-black border border-primary/30 py-2 pl-10 pr-4 text-[10px] outline-none focus:border-primary transition">
                </div>
                <Link :href="route('dashboard.clients.create')" class="bg-primary text-black px-6 py-2 text-[10px] font-black uppercase hover:bg-white transition flex items-center gap-2">
                    <UserPlus :size="14" /> ADD_CLIENT
                </Link>
            </div>
        </div>

        <div class="border border-primary/20 bg-black/80 overflow-x-auto shadow-2xl">
            <table class="w-full text-left text-[11px] border-collapse">
                <thead class="bg-primary/10 border-b border-primary/20 uppercase font-black text-[9px] tracking-widest">
                <tr>
                    <th class="p-4">Client_Info</th>
                    <th class="p-4">Credentials</th>
                    <th class="p-4">Location_Zone</th>
                    <th class="p-4">Billing_Status</th>
                    <th class="p-4">Expiry_Date</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-primary/5 transition group">
                    <td class="p-4">
                        <div class="font-bold text-white uppercase">{{ client.full_name }}</div>
                        <div class="opacity-50 text-[9px]">{{ client.phone_number }}</div>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center gap-2">
                            <span class="bg-primary/10 px-2 py-0.5 rounded border border-primary/20">{{ client.pppoe_username }}</span>
                        </div>
                        <div class="text-[9px] mt-1 opacity-40">{{ client.package_name }}</div>
                    </td>
                    <td class="p-4">
                        <div class="uppercase">{{ client.zone?.name || 'GENERIC' }}</div>
                        <div class="text-[9px] opacity-40 italic">{{ client.sub_zone?.name || 'N/A' }}</div>
                    </td>
                    <td class="p-4">
                        <div class="font-bold text-white">৳ {{ client.monthly_bill }}</div>
                        <span :class="client.status === 'Active' ? 'text-primary' : 'text-red-500'" class="text-[9px] uppercase font-black">
                                [ {{ client.status }} ]
                            </span>
                    </td>
                    <td class="p-4 font-mono">
                            <span :class="new Date(client.expiry_date) < new Date() ? 'text-red-500 underline' : ''">
                                {{ client.expiry_date }}
                            </span>
                    </td>
                    <td class="p-4 text-right">
                        <div class="flex justify-end gap-3 opacity-30 group-hover:opacity-100 transition">
                            <Link :href="route('dashboard.clients.edit', client.id)" class="hover:text-white"><Edit3 :size="16" /></Link>
                            <button @click="deleteClient(client.id)" class="hover:text-red-500"><Trash2 :size="16" /></button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
