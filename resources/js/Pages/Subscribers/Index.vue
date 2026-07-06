<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { ChevronLeft, ChevronRight, Edit3, Search, UserPlus, Users } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

const props = defineProps({
    subscribers: Object,
    filters: Object,
    zones: Array,
    packages: Array,
    stats: Object,
});

const search = ref(props.filters.search ?? '');
const zoneId = ref(props.filters.zone_id ?? '');
const status = ref(props.filters.status ?? '');
const packageName = ref(props.filters.package_name ?? '');

const reload = () => {
    router.get(route('dashboard.subscribers.index'), {
        search: search.value || undefined,
        zone_id: zoneId.value || undefined,
        status: status.value || undefined,
        package_name: packageName.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['subscribers', 'filters', 'stats'],
    });
};

let debounceHandle;
watch(search, () => {
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(reload, 350);
});
watch([zoneId, status, packageName], reload);

const goToPage = (url) => {
    if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
};

const statusClass = (value) => ({
    Active: 'text-primary border-primary/40 bg-primary/10',
    Expired: 'text-red-500 border-red-500/40 bg-red-500/10',
    Suspended: 'text-yellow-500 border-yellow-500/40 bg-yellow-500/10',
}[value] ?? 'text-primary/60 border-primary/20');
</script>

<template>
    <Head title="SUBSCRIBERS" />

    <div class="space-y-6 font-mono text-primary">
        <div class="flex flex-col md:flex-row justify-between gap-4 border-b border-primary/20 pb-6">
            <div>
                <h1 class="text-2xl font-black uppercase italic tracking-tighter flex items-center gap-2">
                    <Users :size="24" /> Subscribers
                </h1>
                <p class="text-[9px] opacity-50">TENANT_SUBSCRIBER_VIEW // TOTAL: {{ stats.total }}</p>
            </div>
            <Link :href="route('dashboard.clients.create')" class="bg-primary text-black px-5 py-2 text-[10px] font-black uppercase hover:bg-white transition flex items-center gap-2 self-start">
                <UserPlus :size="14" /> Add_Subscriber
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="item in [
                ['TOTAL', stats.total],
                ['ACTIVE', stats.active],
                ['EXPIRED', stats.expired],
                ['SUSPENDED', stats.suspended],
            ]" :key="item[0]" class="border border-primary/20 bg-surface p-4">
                <p class="text-[9px] opacity-50 uppercase">{{ item[0] }}</p>
                <p class="text-2xl font-black">{{ item[1] }}</p>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 opacity-50" :size="14" />
                <input v-model="search" type="text" placeholder="SEARCH_NAME_PPPOE_PHONE"
                       class="w-full bg-surface border border-primary/30 py-2 pl-10 pr-4 text-[10px] outline-none focus:border-primary">
            </div>
            <select v-model="zoneId" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none">
                <option value="">ALL_ZONES</option>
                <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
            </select>
            <select v-model="status" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none">
                <option value="">ALL_STATUS</option>
                <option value="Active">Active</option>
                <option value="Expired">Expired</option>
                <option value="Suspended">Suspended</option>
            </select>
            <select v-model="packageName" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none">
                <option value="">ALL_PACKAGES</option>
                <option v-for="p in packages" :key="p.name" :value="p.name">{{ p.name }}</option>
            </select>
        </div>

        <div class="border border-primary/20 bg-surface overflow-x-auto">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-[9px] uppercase tracking-widest">
                    <tr>
                        <th class="p-3">Subscriber</th>
                        <th class="p-3">Package</th>
                        <th class="p-3">Zone</th>
                        <th class="p-3">Monthly</th>
                        <th class="p-3">Expiry</th>
                        <th class="p-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                    <tr v-for="subscriber in subscribers.data" :key="subscriber.id" class="hover:bg-primary/5">
                        <td class="p-3">
                            <div class="font-bold text-ink uppercase">{{ subscriber.full_name }}</div>
                            <div class="text-[9px] opacity-50">{{ subscriber.pppoe_username }} / {{ subscriber.phone_number }}</div>
                        </td>
                        <td class="p-3">{{ subscriber.package_name }}</td>
                        <td class="p-3">{{ subscriber.zone?.name ?? 'N/A' }}</td>
                        <td class="p-3">৳ {{ subscriber.monthly_bill }}</td>
                        <td class="p-3">
                            <span class="block">{{ subscriber.expiry_date }}</span>
                            <span :class="statusClass(subscriber.effective_status)" class="inline-block mt-1 border px-1.5 py-0.5 text-[9px] uppercase">
                                {{ subscriber.effective_status }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <Link :href="route('dashboard.clients.edit', subscriber.id)" class="inline-flex hover:text-ink" title="Edit subscriber">
                                <Edit3 :size="16" />
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="subscribers.data.length === 0">
                        <td colspan="6" class="p-8 text-center opacity-40 text-[10px] uppercase">NO_SUBSCRIBERS_FOUND</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-center text-[10px] uppercase">
            <span class="opacity-50">Page {{ subscribers.current_page }} / {{ subscribers.last_page }}</span>
            <div class="flex gap-2">
                <button :disabled="!subscribers.prev_page_url" @click="goToPage(subscribers.prev_page_url)" class="border border-primary/30 px-3 py-1 flex items-center gap-1 disabled:opacity-30">
                    <ChevronLeft :size="12" /> Prev
                </button>
                <button :disabled="!subscribers.next_page_url" @click="goToPage(subscribers.next_page_url)" class="border border-primary/30 px-3 py-1 flex items-center gap-1 disabled:opacity-30">
                    Next <ChevronRight :size="12" />
                </button>
            </div>
        </div>
    </div>
</template>
