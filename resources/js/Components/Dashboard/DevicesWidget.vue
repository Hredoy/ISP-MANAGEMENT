<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Router, Wifi, WifiOff } from 'lucide-vue-next';
import SkeletonCard from '@/Components/Dashboard/SkeletonCard.vue';

const loading = ref(true);
const status = ref({ total: 0, online: 0, offline: 0 });

onMounted(async () => {
    try {
        const { data } = await axios.get(route('dashboard.widgets.devices-status'));
        status.value = data;
    } catch (e) {
        console.error('DEVICES_STATUS_UNAVAILABLE');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <SkeletonCard v-if="loading" :lines="1" />
    <div v-else class="border border-primary/20 bg-surface p-4">
        <p class="text-[9px] text-primary/50 uppercase mb-1">Devices</p>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 text-xs font-bold">
                <span class="flex items-center gap-1 text-primary"><Wifi :size="14" /> {{ status.online }} online</span>
                <span class="flex items-center gap-1 text-red-500"><WifiOff :size="14" /> {{ status.offline }} offline</span>
            </div>
            <Router :size="16" class="text-primary/30" />
        </div>
    </div>
</template>
