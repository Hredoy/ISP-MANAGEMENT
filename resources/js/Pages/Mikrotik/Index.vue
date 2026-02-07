<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, Edit3, Activity } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
defineProps({ routers: Array });

const deleteRouter = (id) => {
    if (confirm("TERMINATE_CONNECTION?")) {
        router.delete(`/dashboard/mikrotik/${id}`);
    }
};
</script>

<template>
    <Head title="Mikrotik Nodes" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> NETWORK_NODES</h1>
            <Link href="/dashboard/mikrotik/create" class="bg-primary text-black px-4 py-1 text-xs font-bold uppercase hover:bg-white transition-colors">
                + ADD_NEW_NODE
            </Link>
        </div>

        <div class="overflow-x-auto border border-primary/20 bg-black/40">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                <tr>
                    <th class="p-4">IDENTIFIER</th>
                    <th class="p-4">IP_ADDRESS</th>
                    <th class="p-4">STATUS</th>
                    <th class="p-4 text-right">COMMANDS</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <tr v-for="node in routers" :key="node.id" class="hover:bg-primary/5 transition-colors">
                    <td class="p-4 font-bold">{{ node.name }}</td>
                    <td class="p-4 text-primary/70">{{ node.host }}:{{ node.port }}</td>
                    <td class="p-4">
                            <span class="flex items-center text-primary italic">
                                <Activity :size="12" class="mr-2 animate-pulse" /> ONLINE
                            </span>
                    </td>
                    <td class="p-4 text-right space-x-4">
                        <Link :href="`/dashboard/mikrotik/${node.id}/edit`" class="text-primary hover:underline">EDIT</Link>
                        <button @click="deleteRouter(node.id)" class="text-red-500 hover:text-red-300 font-bold">TERMINATE</button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
