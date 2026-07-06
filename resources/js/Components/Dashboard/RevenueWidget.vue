<script setup>
import { Wallet, CalendarDays } from 'lucide-vue-next';

const props = defineProps({
    revenueToday: { type: Number, default: 0 },
    revenueMonth: { type: Number, default: 0 },
});

const format = (value) => `Tk ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4 grid grid-cols-2 gap-4">
        <div>
            <p class="text-[9px] text-primary/50 uppercase">Revenue_Today</p>
            <div class="flex items-center justify-between">
                <span class="text-xl font-black">{{ format(revenueToday) }}</span>
                <Wallet :size="16" class="text-primary/30" />
            </div>
        </div>
        <div>
            <p class="text-[9px] text-primary/50 uppercase">Revenue_This_Month</p>
            <div class="flex items-center justify-between">
                <span class="text-xl font-black">{{ format(revenueMonth) }}</span>
                <CalendarDays :size="16" class="text-primary/30" />
            </div>
        </div>
    </div>
</template>
