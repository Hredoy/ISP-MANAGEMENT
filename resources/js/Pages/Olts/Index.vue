<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Activity, Cable, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { reactive } from 'vue';

defineOptions({ layout: ISPLayout });
defineProps({ olts: Array });

const checks = reactive({});

const deleteOlt = (id) => {
    if (confirm('TERMINATE_OLT_CONNECTION?')) {
        router.delete(`/dashboard/olts/${id}`);
    }
};

const checkConnection = async (olt) => {
    checks[olt.id] = 'CHECKING';

    try {
        const { data } = await axios.post(`/dashboard/olts/${olt.id}/check-connection`);
        checks[olt.id] = data.message ?? 'ONLINE';
    } catch (error) {
        checks[olt.id] = error.response?.data?.message ?? 'OFFLINE';
    }
};

const detectVendor = async (olt) => {
    checks[olt.id] = 'DETECTING_VENDOR';

    try {
        const { data } = await axios.post(`/dashboard/olts/${olt.id}/detect-vendor`);
        checks[olt.id] = `VENDOR_${data.vendor?.toUpperCase()}`;
    } catch (error) {
        checks[olt.id] = error.response?.data?.message ?? 'DETECT_FAILED';
    }
};

const syncOnus = async (olt) => {
    checks[olt.id] = 'SYNCING_ONUS';

    try {
        const { data } = await axios.post(`/dashboard/olts/${olt.id}/sync-onus`);
        checks[olt.id] = `${data.synced ?? 0}_ONUS_SYNCED`;
    } catch (error) {
        checks[olt.id] = error.response?.data?.message ?? 'ONU_SYNC_FAILED';
    }
};
</script>

<template>
    <Head title="OLT Devices" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> OLT_DEVICES</h1>
            <Link href="/dashboard/olts/create" class="inline-flex items-center gap-2 bg-primary text-black px-4 py-1 text-xs font-bold uppercase hover:bg-white transition-colors">
                <Plus :size="14" /> ADD_NEW_OLT
            </Link>
        </div>

        <div class="overflow-x-auto border border-primary/20 bg-surface/40">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                <tr>
                    <th class="p-4">IDENTIFIER</th>
                    <th class="p-4">VENDOR</th>
                    <th class="p-4">IP_ADDRESS</th>
                    <th class="p-4">ONUS</th>
                    <th class="p-4">STATUS</th>
                    <th class="p-4 text-right">COMMANDS</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <tr v-for="node in olts" :key="node.id" class="hover:bg-primary/5 transition-colors">
                    <td class="p-4 font-bold">{{ node.name }}</td>
                    <td class="p-4 text-primary/70 uppercase">{{ node.vendor }}</td>
                    <td class="p-4 text-primary/70">{{ node.host }}:{{ node.port }}</td>
                    <td class="p-4 text-primary/70">
                        <p class="font-bold text-primary">{{ node.onus_count || 0 }}</p>
                        <div v-if="node.onus?.length" class="mt-2 space-y-1">
                            <p v-for="onu in node.onus" :key="onu.id" class="text-[9px]">
                                {{ onu.signal_label || '⚪ unknown' }} / {{ onu.serial_number }} / RX {{ onu.rx_dbm ?? '-' }} dBm
                            </p>
                        </div>
                    </td>
                    <td class="p-4">
                            <span class="flex items-center text-primary italic whitespace-nowrap">
                                <Activity :size="12" class="mr-2 animate-pulse" /> {{ checks[node.id] || 'READY' }}
                            </span>
                    </td>
                    <td class="p-4">
                        <div class="flex justify-end gap-3">
                            <Link :href="`/dashboard/olts/${node.id}/edit`" class="text-primary hover:text-ink" title="Edit">
                                <Pencil :size="15" />
                            </Link>
                            <button @click="deleteOlt(node.id)" class="text-red-500 hover:text-red-300 font-bold" title="Delete">
                                <Trash2 :size="15" />
                            </button>
                            <button @click="checkConnection(node)" class="text-primary/70 hover:text-primary" title="Check connection">
                                <Cable :size="15" />
                            </button>
                            <button @click="detectVendor(node)" class="text-primary/70 hover:text-primary text-[10px] font-bold" title="Auto-detect vendor">
                                AUTO
                            </button>
                            <button @click="syncOnus(node)" class="text-primary/70 hover:text-primary text-[10px] font-bold" title="Sync ONUs">
                                ONU
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
