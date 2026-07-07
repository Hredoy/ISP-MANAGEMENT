<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Activity, Cable, ChevronDown, ChevronUp, GitBranch, Pencil, Plus, Power, PowerOff, RefreshCw, Shield, Star, Trash2, Users } from 'lucide-vue-next';
import { reactive } from 'vue';

defineOptions({ layout: ISPLayout });
const props = defineProps({ routers: Array });

const checks = reactive({});
const stats = reactive({});
const expanded = reactive({});

const deleteRouter = (id) => {
    if (confirm('TERMINATE_CONNECTION?')) {
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

const toggleStats = async (node) => {
    if (expanded[node.id]) {
        expanded[node.id] = false;
        return;
    }

    expanded[node.id] = true;

    if (!stats[node.id]) {
        try {
            const { data } = await axios.get(`/dashboard/mikrotik/${node.id}/stats`);
            stats[node.id] = data;
        } catch (error) {
            stats[node.id] = { error: error.response?.data?.message ?? 'STATS_UNAVAILABLE' };
        }
    }
};

const setMode = (node, mode) => {
    router.patch(`/dashboard/mikrotik/${node.id}/mode`, { mode }, { preserveScroll: true });
};

const syncRouter = (node) => router.post(`/dashboard/mikrotik/${node.id}/sync`, {}, { preserveScroll: true });
const toggleEnabled = (node) => router.post(`/dashboard/mikrotik/${node.id}/${node.is_active ? 'disable' : 'enable'}`, {}, { preserveScroll: true });
const setDefault = (node) => router.post(`/dashboard/mikrotik/${node.id}/set-default`, {}, { preserveScroll: true });
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

        <div class="overflow-x-auto border border-primary/20 bg-surface/40">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                <tr>
                    <th class="p-4">IDENTIFIER</th>
                    <th class="p-4">IP_ADDRESS</th>
                    <th class="p-4">MODE</th>
                    <th class="p-4">STATUS</th>
                    <th class="p-4 text-right">COMMANDS</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <template v-for="node in routers" :key="node.id">
                    <tr class="hover:bg-primary/5 transition-colors" :class="{ 'opacity-40': !node.is_active }">
                        <td class="p-4 font-bold">
                            {{ node.name }}
                            <Star v-if="node.is_default" :size="11" class="inline text-yellow-400 fill-yellow-400 ml-1" />
                            <span v-if="node.location" class="block text-[9px] text-primary/50 font-normal">{{ node.location }}</span>
                        </td>
                        <td class="p-4 text-primary/70">{{ node.host }}:{{ node.port }}</td>
                        <td class="p-4">
                            <select :value="node.mode" @change="setMode(node, $event.target.value)"
                                    class="bg-surface border border-primary/30 text-primary text-[10px] uppercase p-1">
                                <option value="use_global">USE_GLOBAL</option>
                                <option value="demo">DEMO</option>
                                <option value="real">REAL</option>
                            </select>
                            <span class="block text-[9px] text-primary/50 uppercase mt-1">
                                effective: {{ node.effective_mode }} ({{ node.mode_source === 'router_override' ? 'own' : 'global' }})
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="flex items-center text-primary italic whitespace-nowrap">
                                <Activity :size="12" class="mr-2 animate-pulse" /> {{ checks[node.id] || node.status || 'READY' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex justify-end gap-3">
                                <Link :href="`/dashboard/mikrotik/${node.id}/queue-tree`" class="text-primary/70 hover:text-primary" title="Queue tree">
                                    <GitBranch :size="15" />
                                </Link>
                                <Link :href="`/dashboard/mikrotik/${node.id}/firewall`" class="text-primary/70 hover:text-primary" title="Firewall rules">
                                    <Shield :size="15" />
                                </Link>
                                <Link :href="`/dashboard/mikrotik/${node.id}/pppoe-sessions`" class="text-primary/70 hover:text-primary" title="PPPoE sessions">
                                    <Users :size="15" />
                                </Link>
                                <button @click="toggleStats(node)" class="text-primary/70 hover:text-primary" title="Dashboard stats">
                                    <ChevronUp v-if="expanded[node.id]" :size="15" />
                                    <ChevronDown v-else :size="15" />
                                </button>
                                <button @click="checkConnection(node)" class="text-primary/70 hover:text-primary" title="Check connection">
                                    <Cable :size="15" />
                                </button>
                                <button @click="syncRouter(node)" class="text-primary/70 hover:text-primary" title="Sync PPPoE users">
                                    <RefreshCw :size="15" />
                                </button>
                                <button @click="setDefault(node)" class="text-primary/70 hover:text-yellow-400" title="Set as default" :disabled="node.is_default">
                                    <Star :size="15" />
                                </button>
                                <button @click="toggleEnabled(node)" class="text-primary/70 hover:text-primary" :title="node.is_active ? 'Disable' : 'Enable'">
                                    <PowerOff v-if="node.is_active" :size="15" />
                                    <Power v-else :size="15" />
                                </button>
                                <Link :href="`/dashboard/mikrotik/${node.id}/edit`" class="text-primary hover:text-ink" title="Edit">
                                    <Pencil :size="15" />
                                </Link>
                                <button @click="deleteRouter(node.id)" class="text-red-500 hover:text-red-300 font-bold" title="Delete">
                                    <Trash2 :size="15" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expanded[node.id]" class="bg-primary/5">
                        <td colspan="5" class="p-4">
                            <div v-if="!stats[node.id]" class="text-primary/50 text-[10px] uppercase">LOADING_STATS...</div>
                            <div v-else-if="stats[node.id].error" class="text-red-400 text-[10px] uppercase">{{ stats[node.id].error }}</div>
                            <div v-else class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-4 text-[10px] uppercase">
                                <div><p class="text-primary/50">CPU</p><p class="text-primary font-bold">{{ stats[node.id].cpu }}%</p></div>
                                <div><p class="text-primary/50">RAM</p><p class="text-primary font-bold">{{ stats[node.id].ram }} MB</p></div>
                                <div><p class="text-primary/50">Storage</p><p class="text-primary font-bold">{{ stats[node.id].storage }} MB</p></div>
                                <div><p class="text-primary/50">Uptime</p><p class="text-primary font-bold">{{ stats[node.id].uptime }}</p></div>
                                <div><p class="text-primary/50">Version</p><p class="text-primary font-bold">{{ stats[node.id].version ?? 'N/A' }}</p></div>
                                <div><p class="text-primary/50">Connected Users</p><p class="text-primary font-bold">{{ stats[node.id].users }}</p></div>
                                <div><p class="text-primary/50">Total PPP Users</p><p class="text-primary font-bold">{{ stats[node.id].totalPppUsers }}</p></div>
                                <div><p class="text-primary/50">Total Queues</p><p class="text-primary font-bold">{{ stats[node.id].totalQueues }}</p></div>
                                <div class="col-span-2"><p class="text-primary/50">RX/TX</p><p class="text-primary font-bold">{{ stats[node.id].rx }} / {{ stats[node.id].tx }} Mbps</p></div>
                                <div class="col-span-2"><p class="text-primary/50">Last Sync</p><p class="text-primary font-bold">{{ stats[node.id].lastSyncAt ?? 'NEVER' }}</p></div>
                            </div>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
