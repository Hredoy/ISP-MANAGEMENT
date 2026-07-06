<script setup>
import { UserCheck, UserX, UserMinus } from 'lucide-vue-next';

defineProps({
    active: { type: Number, default: 0 },
    suspended: { type: Number, default: 0 },
    expired: { type: Number, default: 0 },
});
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4 grid grid-cols-3 gap-4">
        <div>
            <p class="text-[9px] text-primary/50 uppercase">Active</p>
            <div class="flex items-center justify-between">
                <span class="text-xl font-black text-primary">{{ active }}</span>
                <UserCheck :size="16" class="text-primary/30" />
            </div>
        </div>
        <div>
            <p class="text-[9px] text-primary/50 uppercase">Suspended</p>
            <div class="flex items-center justify-between">
                <span class="text-xl font-black text-yellow-500">{{ suspended }}</span>
                <UserMinus :size="16" class="text-primary/30" />
            </div>
        </div>
        <div>
            <p class="text-[9px] text-primary/50 uppercase">Expired</p>
            <div class="flex items-center justify-between">
                <span class="text-xl font-black text-red-500">{{ expired }}</span>
                <UserX :size="16" class="text-primary/30" />
            </div>
        </div>
    </div>
</template>
