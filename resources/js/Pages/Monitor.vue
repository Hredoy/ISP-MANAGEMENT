<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

const props = defineProps({ router: Object });
defineOptions({ layout: ISPLayout });

// Data Refs
const cpuSeries = ref([{ name: 'CPU Load', data: [] }]);
const userCount = ref(0);
const systemInfo = ref({ ram: 0, storage: 0, uptime: '00:00:00' });

// Chart Options (Hacker Theme)
const chartOptions = {
    chart: { id: 'live-cpu', animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 1000 } }, toolbar: { show: false }, background: 'transparent' },
    colors: ['#00FF41'], // Matrix Green
    stroke: { curve: 'smooth', width: 3 },
    grid: { borderColor: '#111', xaxis: { lines: { show: true } } },
    xaxis: { labels: { show: false }, axisBorder: { show: false } },
    yaxis: { max: 100, labels: { style: { colors: '#00FF41', fontFamily: 'monospace' } } },
    tooltip: { theme: 'dark' }
};

const fetchData = async () => {
    try {
        const res = await axios.get(`/dashboard/mikrotik/${props.router.id}/stats`);
        const { cpu, users, ram, storage, uptime } = res.data;

        // Update CPU Graph (Keep last 20 points)
        cpuSeries.value[0].data.push(cpu);
        if (cpuSeries.value[0].data.length > 20) cpuSeries.value[0].data.shift();

        // Update Other Stats
        userCount.ref = users;
        systemInfo.value = { ram, storage, uptime };
    } catch (e) {
        console.error("CONNECTION_INTERRUPTED");
    }
};

let interval;
onMounted(() => {
    fetchData();
    interval = setInterval(fetchData, 3000); // Pulse every 3 seconds
});
onUnmounted(() => clearInterval(interval));
</script>

<template>
    <div class="space-y-6">
        <div class="border-b border-primary/20 pb-4 flex justify-between items-end">
            <h1 class="text-2xl font-black text-primary tracking-tighter uppercase">Monitoring: {{ router.name }}</h1>
            <span class="text-[10px] text-primary animate-pulse">● LIVE_DATA_STREAM</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="border border-primary/30 p-4 bg-black">
                <p class="text-[10px] opacity-60">ACTIVE_USERS</p>
                <p class="text-3xl font-black">{{ userCount }}</p>
            </div>
            <div class="border border-primary/30 p-4 bg-black">
                <p class="text-[10px] opacity-60">RAM_USAGE</p>
                <p class="text-3xl font-black">{{ systemInfo.ram }}<span class="text-xs">MB</span></p>
            </div>
            <div class="border border-primary/30 p-4 bg-black">
                <p class="text-[10px] opacity-60">STORAGE_USED</p>
                <p class="text-3xl font-black">{{ systemInfo.storage }}<span class="text-xs">MB</span></p>
            </div>
            <div class="border border-primary/30 p-4 bg-black">
                <p class="text-[10px] opacity-60">UPTIME</p>
                <p class="text-xl font-black mt-2">{{ systemInfo.uptime }}</p>
            </div>
        </div>

        <div class="border border-primary/30 bg-black p-6">
            <div class="flex justify-between mb-4">
                <h3 class="text-xs font-bold uppercase tracking-widest text-primary">CPU_LOAD_HISTORY (%)</h3>
                <span class="text-[10px] text-primary font-mono">{{ cpuSeries[0].data.at(-1) }}%</span>
            </div>
            <VueApexCharts type="line" height="300" :options="chartOptions" :series="cpuSeries" />
        </div>
    </div>
</template>
