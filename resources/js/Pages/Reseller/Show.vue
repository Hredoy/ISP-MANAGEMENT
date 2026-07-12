<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, ChevronLeft, Wallet, Pencil, CheckCircle } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ reseller: Object, clients: Object, commissions: Object, summary: Object });

const money = (v) => `৳ ${Number(v ?? 0).toLocaleString()}`;

const payCommission = (commissionId) => {
    if (confirm('Mark this commission as paid?')) {
        router.post(route('dashboard.resellers.commissions.pay', [props.reseller.id, commissionId]));
    }
};
</script>

<template>
    <Head :title="`RESELLER: ${reseller.name}`" />
    <div class="space-y-8 font-mono text-primary">
        <div class="flex justify-between items-start">
            <div>
                <Link :href="route('dashboard.resellers.index')" class="text-[10px] opacity-50 hover:opacity-100 flex items-center gap-1 mb-2">
                    <ChevronLeft :size="14" /> BACK_TO_RESELLERS
                </Link>
                <h1 class="text-xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                    <Users :size="20" /> {{ reseller.name }}
                    <span :class="reseller.status === 'active' ? 'text-green-400' : 'text-red-400'" class="text-[9px] font-bold tracking-widest">
                        [{{ reseller.status }}]
                    </span>
                </h1>
                <p class="text-[11px] text-primary/50 mt-1">
                    {{ reseller.commission_rate }}% commission
                    <span v-if="reseller.parent"> · Under: {{ reseller.parent.name }}</span>
                </p>
            </div>
            <Link :href="route('dashboard.resellers.edit', reseller.id)" class="border border-primary/30 px-4 py-2 text-[10px] font-black uppercase hover:bg-primary/10 transition flex items-center gap-2">
                <Pencil :size="14" /> EDIT
            </Link>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1">Wallet Balance</p>
                <p class="text-lg font-black text-yellow-400 flex items-center gap-1"><Wallet :size="16" /> {{ money(summary.wallet_balance) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1">Total Earned</p>
                <p class="text-lg font-black text-green-400">{{ money(summary.commission_total) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1">This Month</p>
                <p class="text-lg font-black text-primary">{{ money(summary.commission_month) }}</p>
            </div>
            <div class="border border-primary/20 bg-surface/80 p-4">
                <p class="text-[9px] uppercase text-primary/50 mb-1">Clients</p>
                <p class="text-lg font-black text-ink">{{ summary.clients_count }}</p>
            </div>
        </div>

        <!-- Sub-resellers -->
        <div v-if="reseller.children?.length" class="border border-primary/20 p-4">
            <h3 class="text-[10px] uppercase font-bold text-primary/60 mb-3">Sub-Resellers ({{ reseller.children.length }})</h3>
            <div class="flex flex-wrap gap-2">
                <Link v-for="child in reseller.children" :key="child.id"
                      :href="route('dashboard.resellers.show', child.id)"
                      class="text-[10px] border border-primary/20 px-3 py-1 hover:bg-primary/10 transition">
                    {{ child.name }} ({{ child.commission_rate }}%)
                </Link>
            </div>
        </div>

        <!-- Clients -->
        <div>
            <h3 class="text-[11px] uppercase font-bold text-primary/60 mb-3">Clients ({{ clients.total }})</h3>
            <div v-if="clients.data.length" class="border border-primary/20 overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead class="bg-surface/80 border-b border-primary/20">
                        <tr>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Name</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">PPPoE</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Package</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Monthly Bill</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in clients.data" :key="c.id" class="border-b border-primary/10 hover:bg-primary/5">
                            <td class="px-4 py-3 font-bold text-ink">{{ c.full_name }}</td>
                            <td class="px-4 py-3 text-primary/60">{{ c.pppoe_username }}</td>
                            <td class="px-4 py-3">{{ c.package_name }}</td>
                            <td class="px-4 py-3 text-right">{{ money(c.monthly_bill) }}</td>
                            <td class="px-4 py-3">
                                <span :class="c.effective_status === 'Active' ? 'text-green-400' : 'text-red-400'" class="text-[9px] uppercase font-bold">{{ c.effective_status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-primary/40 text-sm py-4">No clients yet.</p>
        </div>

        <!-- Commission Log -->
        <div>
            <h3 class="text-[11px] uppercase font-bold text-primary/60 mb-3">Commission Log ({{ commissions.total }})</h3>
            <div v-if="commissions.data.length" class="border border-primary/20 overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead class="bg-surface/80 border-b border-primary/20">
                        <tr>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Date</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Client</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Payment</th>
                            <th class="text-right px-4 py-3 text-primary/60 uppercase">Commission</th>
                            <th class="text-left px-4 py-3 text-primary/60 uppercase">Status</th>
                            <th class="px-4 py-3"></th>
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
                            <td class="px-4 py-3 text-right">
                                <button v-if="c.status === 'pending'" @click="payCommission(c.id)" class="text-[9px] border border-green-400/30 text-green-400 px-2 py-1 hover:bg-green-400/10 transition flex items-center gap-1">
                                    <CheckCircle :size="11" /> MARK_PAID
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-primary/40 text-sm py-4">No commissions yet.</p>
        </div>
    </div>
</template>
