<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Router, Users, Cpu, HardDrive, Clock, ArrowDown, ArrowUp, Activity } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({ routers: Array });
defineOptions({ layout: ISPLayout });

// --- STATE ---
const selectedRouterId = ref(props.routers.length > 0 ? props.routers[0].id : null);
const stats = ref({ cpu: 0, users: 0, ram: 0, storage: 0, uptime: '00:00:00', activeIps: 0 });

// Individual refs for current traffic display
const currentRx = ref(0);
const currentTx = ref(0);

// Series for CPU Chart
const cpuSeries = ref([{ name: 'CPU Load', data: new Array(20).fill(0) }]);

// Series for Traffic Chart (Download/Upload)
const trafficSeries = ref([
    { name: 'Download (Rx)', data: new Array(20).fill(0) },
    { name: 'Upload (Tx)', data: new Array(20).fill(0) }
]);

// --- CHART OPTIONS ---
const commonChartOptions = {
    chart: {
        animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 3000 } },
        toolbar: { show: false },
        background: 'transparent'
    },
    stroke: { curve: 'smooth', width: 2 },
    grid: { borderColor: '#111' },
    xaxis: { labels: { show: false }, axisBorder: { show: false } },
    tooltip: { theme: 'dark' },
    legend: { labels: { colors: '#00FF41' }, fontFamily: 'monospace' }
};

const cpuOptions = {
    ...commonChartOptions,
    chart: { ...commonChartOptions.chart, id: 'cpu-graph' },
    colors: ['#00FF41'],
    yaxis: { max: 100, labels: { style: { colors: '#00FF41' } } },
};

const trafficOptions = {
    ...commonChartOptions,
    chart: { ...commonChartOptions.chart, id: 'traffic-graph' },
    colors: ['#00FF41', '#00A3FF'], // Green for Down, Blue for Up
    yaxis: { labels: { style: { colors: '#00FF41' } } },
};

// --- LOGIC ---
let interval = null;

const fetchStats = async () => {
    if (!selectedRouterId.value) return;

    try {
        const res = await axios.get(`/dashboard/mikrotik/${selectedRouterId.value}/stats`);
        const data = res.data;

        // 1. Update Numeric Status Cards
        stats.value = data;
        currentRx.value = data.rx;
        currentTx.value = data.tx;

        // 2. Update Traffic Chart (Rx/Tx)
        const newRx = [...trafficSeries.value[0].data, data.rx].slice(-20);
        const newTx = [...trafficSeries.value[1].data, data.tx].slice(-20);
        trafficSeries.value = [
            { ...trafficSeries.value[0], data: newRx },
            { ...trafficSeries.value[1], data: newTx }
        ];

        // 3. Update CPU Chart
        const newCpuData = [...cpuSeries.value[0].data, data.cpu].slice(-20);
        cpuSeries.value = [{ ...cpuSeries.value[0], data: newCpuData }];

    } catch (e) {
        console.error("NODE_UNREACHABLE: Handshake failed.");
    }
};

// Watcher: Reset data when router changes to prevent ghost lines
watch(selectedRouterId, () => {
    cpuSeries.value = [{ name: 'CPU Load', data: new Array(20).fill(0) }];
    trafficSeries.value = [
        { name: 'Download (Rx)', data: new Array(20).fill(0) },
        { name: 'Upload (Tx)', data: new Array(20).fill(0) }
    ];
    fetchStats();
});

onMounted(() => {
    fetchStats();
    interval = setInterval(fetchStats, 3000);
});

onUnmounted(() => clearInterval(interval));
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-primary/20 pb-6">
            <h1 class="text-2xl font-black text-primary uppercase italic tracking-tighter flex items-center gap-2">
                <Activity class="animate-pulse" /> Live_Node_Monitor
            </h1>

            <div class="bg-black border border-primary/40 p-1 px-3 flex items-center gap-2">
                <Router :size="14" class="text-primary" />
                <select v-model="selectedRouterId" class="bg-transparent text-primary text-[10px] font-bold uppercase border-none focus:ring-0 outline-none">
                    <option v-for="r in routers" :key="r.id" :value="r.id" class="bg-black text-primary">
                        {{ r.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="border border-primary/20 bg-black p-4">
                <p class="text-[9px] text-primary/50 uppercase">Active_Users</p>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-black">{{ stats.users }}</span>
                    <Users :size="18" class="text-primary/30" />
                </div>
            </div>
            <div class="border border-primary/20 bg-black p-4">
                <p class="text-[9px] text-primary/50 uppercase">RAM_Usage</p>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-black">{{ stats.ram }}<small class="text-xs">MB</small></span>
                    <Cpu :size="18" class="text-primary/30" />
                </div>
            </div>
            <div class="border border-primary/20 bg-black p-4">
                <p class="text-[9px] text-primary/50 uppercase">Storage</p>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-black">{{ stats.storage }}<small class="text-xs">MB</small></span>
                    <HardDrive :size="18" class="text-primary/30" />
                </div>
            </div>
            <div class="border border-primary/20 bg-black p-4 col-span-1 lg:col-span-2">
                <p class="text-[9px] text-primary/50 uppercase">Uptime</p>
                <div class="flex items-center justify-between">
                    <span class="text-xl font-black truncate">{{ stats.uptime }}</span>
                    <Clock :size="18" class="text-primary/30" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="border border-primary/20 bg-black p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-bold text-primary underline uppercase tracking-widest">>> Traffic_BPS</h3>
                    <div class="flex gap-4 text-[11px] font-bold">
                        <span class="flex items-center gap-1 text-primary"><ArrowDown :size="12"/> {{ currentRx }} Mbps</span>
                        <span class="flex items-center gap-1 text-blue-400"><ArrowUp :size="12"/> {{ currentTx }} Mbps</span>
                    </div>
                </div>
                <VueApexCharts type="area" height="250" :options="trafficOptions" :series="trafficSeries" />
            </div>

            <div class="border border-primary/20 bg-black p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-bold text-primary underline uppercase tracking-widest">>> CPU_Load_%</h3>
                    <span class="text-primary font-bold text-xs">{{ stats.cpu }}%</span>
                </div>
                <VueApexCharts type="line" height="250" :options="cpuOptions" :series="cpuSeries" />
            </div>
        </div>
    </div>
</template>
