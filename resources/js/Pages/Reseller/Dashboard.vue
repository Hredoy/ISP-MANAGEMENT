<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Wallet, Users, TrendingUp, Clock } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ reseller: Object, clients: Object, commissions: Object, summary: Object });

const money = (v) => `৳ ${Number(v ?? 0).toLocaleString()}`;
</script>

<template>
    <Head title="MY_DASHBOARD" />
    <div class="space-y-8 font-mono text-primary">
        <div>
            <h1 class="text-xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                <Users :size="20" /> Reseller_Dashboard
            </h1>
            <p class="text-[11px] text-primary/50 mt-1">{{ reseller.name }} · {{ reseller.commission_rate }}% commission rate</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1 flex items-center gap-1"><Wallet :size="12" /> Wallet</p>
                <p class="text-xl font-black text-yellow-400">{{ money(summary.wallet_balance) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1 flex items-center gap-1"><TrendingUp :size="12" /> This Month</p>
                <p class="text-xl font-black text-green-400">{{ money(summary.commission_month) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1 flex items-center gap-1"><Clock :size="12" /> Pending</p>
                <p class="text-xl font-black text-orange-400">{{ money(summary.commission_pending) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1 flex items-center gap-1"><Users :size="12" /> Clients</p>
                <p class="text-xl font-black text-ink">{{ summary.clients_active }} <span class="text-primary/40 text-xs">/ {{ summary.clients_total }}</span></p>
            </div>
        </div>

        <!-- My Clients -->
        <div>
            <h3 class="text-[11px] uppercase font-bold text-primary/60 mb-3">My Clients ({{ clients.total }})</h3>
            <div v-if="clients.data.length" class="border border-primary/20 overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead class="bg-surface/80 border-b border-primary/20">
                        <tr>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Name</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">PPPoE</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Package</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Monthly Bill</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Expiry</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in clients.data" :key="c.id" class="border-b border-primary/10 hover:bg-primary/5">
                            <td class="px-4 py-3 font-bold text-ink">{{ c.full_name }}</td>
                            <td class="px-4 py-3 text-primary/60">{{ c.pppoe_username }}</td>
                            <td class="px-4 py-3">{{ c.package_name }}</td>
                            <td class="px-4 py-3 text-right">{{ money(c.monthly_bill) }}</td>
                            <td class="px-4 py-3 text-primary/60">{{ c.expiry_date }}</td>
                            <td class="px-4 py-3">
                                <span :class="c.effective_status === 'Active' ? 'text-green-400' : 'text-red-400'" class="text-[9px] uppercase font-bold">{{ c.effective_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-primary/40 text-sm py-4">No clients assigned yet.</p>
        </div>

        <!-- Commission History -->
        <div>
            <h3 class="text-[11px] uppercase font-bold text-primary/60 mb-3">Commission History ({{ commissions.total }})</h3>
            <div v-if="commissions.data.length" class="border border-primary/20 overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead class="bg-surface/80 border-b border-primary/20">
                        <tr>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Date</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Client</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Payment</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Commission</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in commissions.data" :key="c.id" class="border-b border-primary/10 hover:bg-primary/5">
                            <td class="px-4 py-3 text-primary/60">{{ new Date(c.created_at).toLocaleDateString() }}</td>
                            <td class="px-4 py-3">{{ c.client?.full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ money(c.payment?.amount) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-green-400">{{ money(c.amount) }}</td>
                            <td class="px-4 py-3">
                                <span :class="c.status === 'paid' ? 'text-green-400' : 'text-yellow-400'" class="text-[9px] uppercase font-bold">{{ c.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-primary/40 text-sm py-4">No commissions yet.</p>
        </div>
    </div>
</template>
