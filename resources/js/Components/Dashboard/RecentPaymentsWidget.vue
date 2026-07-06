<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { Receipt } from 'lucide-vue-next';
import SkeletonCard from '@/Components/Dashboard/SkeletonCard.vue';

const props = defineProps({ initialPayments: { type: Array, default: () => [] } });

const loading = ref(props.initialPayments.length === 0);
const payments = ref(props.initialPayments);
let interval = null;

/**
 * Polled refresh rather than a true WebSocket push — Soketi/real-time infra doesn't exist yet
 * (that's the separate Smart ticket system / WebSocket alerts task). 15s keeps this feeling
 * "live" without needing that infrastructure.
 */
const refresh = async () => {
    try {
        const { data } = await axios.get(route('dashboard.widgets.recent-payments'));
        payments.value = data.payments;
    } catch (e) {
        console.error('RECENT_PAYMENTS_UNAVAILABLE');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    refresh();
    interval = setInterval(refresh, 15000);
});
onUnmounted(() => clearInterval(interval));

const format = (value) => `Tk ${Number(value).toLocaleString()}`;
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4">
        <h3 class="text-[10px] font-bold text-primary underline uppercase tracking-widest mb-3 flex items-center gap-2">
            <Receipt :size="14" /> Recent_Payments
        </h3>

        <SkeletonCard v-if="loading" :lines="4" />
        <p v-else-if="payments.length === 0" class="text-[11px] text-primary/50">No payments recorded yet.</p>
        <ul v-else class="space-y-2">
            <li v-for="payment in payments" :key="payment.id" class="flex items-center justify-between text-[11px] border-b border-primary/10 pb-2 last:border-0">
                <span class="truncate">{{ payment.client?.full_name ?? 'Unknown client' }}</span>
                <span class="font-bold" :class="payment.status === 'completed' ? 'text-primary' : 'text-yellow-500'">
                    {{ format(payment.amount) }}
                </span>
            </li>
        </ul>
    </div>
</template>
