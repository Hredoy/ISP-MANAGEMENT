<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Activity, Cable, Pencil, Plus, Router, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

defineOptions({ layout: ISPLayout });
defineProps({ routers: Array });

const checks = reactive({});

const deleteRouter = (id) => {
    if (confirm("TERMINATE_CONNECTION?")) {
        router.delete(`/dashboard/mikrotik/${id}`);
    }
};

const checkConnection = async (node) => {
    checks[node.id] = 'CHECKING';

    try {
        const { data } = await axios.post(`/dashboard/mikrotik/${node.id}/check-connection`);
        checks[node.id] = data.uptime ? `ONLINE ${data.uptime}` : data.message;
    } catch (error) {
        checks[node.id] = error.response?.data?.message ?? 'OFFLINE';
    }
};

const addOlt = () => {
    alert('OLT_MODULE_NOT_CONFIGURED');
};
</script>

<template>
    <Head title="Mikrotik Nodes" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> NETWORK_NODES</h1>
            <Link href="/dashboard/mikrotik/create" class="inline-flex items-center gap-2 bg-primary text-black px-4 py-1 text-xs font-bold uppercase hover:bg-white transition-colors">
                <Plus :size="14" /> ADD_NEW_NODE
            </Link>
        </div>

        <div class="overflow-x-auto border border-primary/20 bg-black/40">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                <tr>
                    <th class="p-4">IDENTIFIER</th>
                    <th class="p-4">IP_ADDRESS</th>
                    <th class="p-4">DESCRIPTION</th>
                    <th class="p-4">STATUS</th>
                    <th class="p-4 text-right">COMMANDS</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <tr v-for="node in routers" :key="node.id" class="hover:bg-primary/5 transition-colors">
                    <td class="p-4 font-bold">{{ node.name }}</td>
                    <td class="p-4 text-primary/70">{{ node.host }}:{{ node.port }}</td>
                    <td class="p-4 text-primary/60 max-w-xs truncate">{{ node.description || 'N/A' }}</td>
                    <td class="p-4">
                            <span class="flex items-center text-primary italic whitespace-nowrap">
                                <Activity :size="12" class="mr-2 animate-pulse" /> {{ checks[node.id] || 'READY' }}
                            </span>
                    </td>
                    <td class="p-4">
                        <div class="flex justify-end gap-3">
                            <Link :href="`/dashboard/mikrotik/${node.id}/edit`" class="text-primary hover:text-white" title="Edit">
                                <Pencil :size="15" />
                            </Link>
                            <button @click="deleteRouter(node.id)" class="text-red-500 hover:text-red-300 font-bold" title="Delete">
                                <Trash2 :size="15" />
                            </button>
                            <button @click="addOlt" class="text-primary/70 hover:text-primary" title="Add OLT">
                                <Router :size="15" />
                            </button>
                            <button @click="checkConnection(node)" class="text-primary/70 hover:text-primary" title="Check connection">
                                <Cable :size="15" />
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
