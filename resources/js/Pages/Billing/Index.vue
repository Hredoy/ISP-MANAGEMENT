<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { CreditCard, ReceiptText, Save } from 'lucide-vue-next';
import { createToaster } from '@meforma/vue-toaster';

defineOptions({ layout: ISPLayout });

const toaster = createToaster();
const props = defineProps({
    payments: Object,
    clients: Array,
    filters: Object,
    settings: Object,
    summary: Object,
});

const selectedClient = computed(() => props.clients.find((client) => client.id === form.client_id));

const form = useForm({
    client_id: props.clients[0]?.id ?? '',
    amount: props.clients[0]?.monthly_bill ?? '',
    method: props.settings.payment_methods?.[0] ?? 'cash',
    status: 'completed',
    billing_period: new Date().toISOString().slice(0, 10),
    paid_at: new Date().toISOString().slice(0, 10),
    extend_days: 30,
    note: '',
});

const methodFilter = ref(props.filters.method ?? '');
const statusFilter = ref(props.filters.status ?? '');

const syncClientAmount = () => {
    if (selectedClient.value) form.amount = selectedClient.value.monthly_bill;
};

const submit = () => {
    form.post(route('dashboard.billing.payments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toaster.info('>> PAYMENT_RECORDED');
            form.note = '';
        },
    });
};

const reload = () => {
    router.get(route('dashboard.billing.index'), {
        method: methodFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
};

const money = (value) => `৳ ${Number(value ?? 0).toLocaleString()}`;
</script>

<template>
    <Head title="BILLING" />

    <div class="space-y-6 font-mono text-primary">
        <div class="border-b border-primary/20 pb-6">
            <h1 class="text-2xl font-black uppercase italic tracking-tighter flex items-center gap-2">
                <CreditCard :size="24" /> Billing
            </h1>
            <p class="text-[9px] opacity-50">PAYMENT_COLLECTION // EXPIRY_RENEWAL // LEDGER</p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="item in [
                ['TODAY', money(summary.revenue_today)],
                ['MONTH', money(summary.revenue_month)],
                ['DUE', money(summary.due_amount)],
                ['EXPIRED', summary.expired_clients],
            ]" :key="item[0]" class="border border-primary/20 bg-surface p-4">
                <p class="text-[9px] opacity-50 uppercase">{{ item[0] }}</p>
                <p class="text-xl font-black">{{ item[1] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <form @submit.prevent="submit" class="border border-primary/20 bg-surface p-4 space-y-4">
                <h2 class="text-[11px] font-black uppercase flex items-center gap-2"><ReceiptText :size="16" /> Record_Payment</h2>

                <label class="block text-[10px] uppercase">
                    Subscriber
                    <select v-model="form.client_id" @change="syncClientAmount" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                        <option v-for="client in clients" :key="client.id" :value="client.id">
                            {{ client.full_name }} / {{ client.pppoe_username }}
                        </option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-[10px] uppercase">
                        Amount
                        <input v-model="form.amount" type="number" min="1" step="0.01" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                    <label class="block text-[10px] uppercase">
                        Extend_Days
                        <input v-model="form.extend_days" type="number" min="0" max="365" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-[10px] uppercase">
                        Method
                        <select v-model="form.method" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                            <option v-for="method in (settings.payment_methods ?? ['cash', 'bkash', 'nagad', 'bank'])" :key="method" :value="method">{{ method }}</option>
                        </select>
                    </label>
                    <label class="block text-[10px] uppercase">
                        Status
                        <select v-model="form.status" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                            <option value="completed">completed</option>
                            <option value="pending">pending</option>
                            <option value="partial">partial</option>
                            <option value="failed">failed</option>
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-[10px] uppercase">
                        Billing_Period
                        <input v-model="form.billing_period" type="date" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                    <label class="block text-[10px] uppercase">
                        Paid_At
                        <input v-model="form.paid_at" type="date" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                </div>

                <label class="block text-[10px] uppercase">
                    Note
                    <textarea v-model="form.note" rows="3" class="mt-1 w-full bg-black/20 border border-primary/30 p-2"></textarea>
                </label>

                <div v-if="Object.keys(form.errors).length" class="text-red-400 text-[10px] space-y-1">
                    <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                </div>

                <button type="submit" :disabled="form.processing" class="bg-primary text-black px-5 py-2 text-[10px] font-black uppercase hover:bg-white transition flex items-center gap-2 disabled:opacity-50">
                    <Save :size="14" /> Save_Payment
                </button>
            </form>

            <div class="xl:col-span-2 border border-primary/20 bg-surface overflow-x-auto">
                <div class="flex flex-col md:flex-row gap-3 p-4 border-b border-primary/10">
                    <select v-model="statusFilter" @change="reload" class="bg-black/20 border border-primary/30 p-2 text-[10px]">
                        <option value="">ALL_STATUS</option>
                        <option value="completed">completed</option>
                        <option value="pending">pending</option>
                        <option value="partial">partial</option>
                        <option value="failed">failed</option>
                    </select>
                    <select v-model="methodFilter" @change="reload" class="bg-black/20 border border-primary/30 p-2 text-[10px]">
                        <option value="">ALL_METHODS</option>
                        <option v-for="method in (settings.payment_methods ?? ['cash', 'bkash', 'nagad', 'bank'])" :key="method" :value="method">{{ method }}</option>
                    </select>
                </div>
                <table class="w-full text-left text-[11px]">
                    <thead class="bg-primary/10 text-[9px] uppercase tracking-widest">
                        <tr>
                            <th class="p-3">Subscriber</th>
                            <th class="p-3">Amount</th>
                            <th class="p-3">Method</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                        <tr v-for="payment in payments.data" :key="payment.id">
                            <td class="p-3">
                                <div class="font-bold text-ink">{{ payment.client?.full_name ?? 'UNMATCHED' }}</div>
                                <div class="text-[9px] opacity-50">{{ payment.client?.pppoe_username ?? payment.id }}</div>
                            </td>
                            <td class="p-3">{{ money(payment.amount) }}</td>
                            <td class="p-3 uppercase">{{ payment.method }}</td>
                            <td class="p-3 uppercase">{{ payment.status }}</td>
                            <td class="p-3">{{ payment.paid_at ?? payment.created_at }}</td>
                        </tr>
                        <tr v-if="payments.data.length === 0">
                            <td colspan="5" class="p-8 text-center opacity-40 text-[10px] uppercase">NO_PAYMENTS_FOUND</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
