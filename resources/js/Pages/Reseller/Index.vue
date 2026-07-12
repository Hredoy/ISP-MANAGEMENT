<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, Plus, Pencil, Trash2, Eye, Wallet } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ resellers: Array });

const money = (v) => `৳ ${Number(v ?? 0).toLocaleString()}`;

const del = (id) => {
    if (confirm('Delete this reseller?')) {
        router.delete(route('dashboard.resellers.destroy', id));
    }
};
</script>

<template>
    <Head title="RESELLERS" />
    <div class="space-y-6 font-mono text-primary">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                <Users :size="20" /> Reseller_Network
            </h1>
            <Link :href="route('dashboard.resellers.create')" class="bg-primary text-black px-4 py-2 text-[10px] font-black uppercase hover:bg-white transition">
                + ADD_RESELLER
            </Link>
        </div>

        <div v-if="!resellers.length" class="text-center py-16 text-primary/40 text-sm">
            NO_RESELLERS_YET — add your first reseller above.
        </div>

        <div v-else class="border border-primary/20 overflow-x-auto">
            <table class="w-full text-[11px]">
                <thead class="bg-surface/80 border-b border-primary/20">
                    <tr>
                        <th class="text-left px-4 py-3 text-primary/60 uppercase">Name</th>
                        <th class="text-left px-4 py-3 text-primary/60 uppercase">Phone</th>
                        <th class="text-left px-4 py-3 text-primary/60 uppercase">Parent</th>
                        <th class="text-right px-4 py-3 text-primary/60 uppercase">Commission %</th>
                        <th class="text-right px-4 py-3 text-primary/60 uppercase">Clients</th>
                        <th class="text-right px-4 py-3 text-primary/60 uppercase">Total Earned</th>
                        <th class="text-right px-4 py-3 text-primary/60 uppercase">Wallet</th>
                        <th class="text-left px-4 py-3 text-primary/60 uppercase">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in resellers" :key="r.id" class="border-b border-primary/10 hover:bg-primary/5 transition">
                        <td class="px-4 py-3 font-bold text-ink">{{ r.name }}</td>
                        <td class="px-4 py-3 text-primary/60">{{ r.phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-primary/60">{{ r.parent?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-bold text-primary">{{ r.commission_rate }}%</td>
                        <td class="px-4 py-3 text-right">{{ r.clients_count ?? 0 }}</td>
                        <td class="px-4 py-3 text-right font-bold text-green-500">{{ money(r.commissions_sum_amount) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-yellow-500 flex items-center justify-end gap-1">
                            <Wallet :size="12" /> {{ money(r.wallet_balance) }}
                        </td>
                        <td class="px-4 py-3">
                            <span :class="r.status === 'active' ? 'text-green-400' : 'text-red-400'" class="uppercase text-[9px] font-bold tracking-widest">
                                {{ r.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3 justify-end">
                                <Link :href="route('dashboard.resellers.show', r.id)" class="text-primary/40 hover:text-primary transition">
                                    <Eye :size="14" />
                                </Link>
                                <Link :href="route('dashboard.resellers.edit', r.id)" class="text-primary/40 hover:text-primary transition">
                                    <Pencil :size="14" />
                                </Link>
                                <button @click="del(r.id)" class="text-red-400/60 hover:text-red-400 transition">
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
