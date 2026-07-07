<script setup>
import { computed, h, ref, watch } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, Search, UserPlus, UploadCloud, Trash2, Edit3, PauseCircle, PlayCircle, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { createToaster } from '@meforma/vue-toaster';
import { FlexRender, createColumnHelper, getCoreRowModel, useVueTable } from '@tanstack/vue-table';
import { useVirtualizer } from '@tanstack/vue-virtual';

const toaster = createToaster();
defineOptions({ layout: ISPLayout });
const props = defineProps({
    clients: Object, // Laravel paginator: { data, current_page, last_page, total, per_page, ... }
    filters: Object,
    zones: Array,
    packages: Array,
});

const search = ref(props.filters.search ?? '');
const zoneId = ref(props.filters.zone_id ?? '');
const status = ref(props.filters.status ?? '');
const packageName = ref(props.filters.package_name ?? '');
const sort = ref(props.filters.sort ?? 'created_at');
const direction = ref(props.filters.direction ?? 'desc');

const reload = () => {
    router.get(route('dashboard.clients.index'), {
        search: search.value || undefined,
        zone_id: zoneId.value || undefined,
        status: status.value || undefined,
        package_name: packageName.value || undefined,
        sort: sort.value,
        direction: direction.value,
        per_page: props.clients.per_page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['clients', 'filters'],
    });
};

let debounceHandle;
const debouncedReload = () => {
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(reload, 350);
};

watch(search, debouncedReload);
watch([zoneId, status, packageName], reload);

const toggleSort = (column) => {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    reload();
};

const goToPage = (url) => {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true, only: ['clients', 'filters'] });
};

const statusStyles = {
    Active: 'text-primary border-primary/40 bg-primary/10',
    Suspended: 'text-yellow-500 border-yellow-500/40 bg-yellow-500/10',
    Expired: 'text-red-500 border-red-500/40 bg-red-500/10',
};

const deleteClient = (id, name) => {
    if (confirm(`CRITICAL_ACTION: Are you sure you want to terminate ${name}? This will remove them from the Router and Database.`)) {
        router.delete(route('dashboard.clients.destroy', id), {
            onSuccess: () => toaster.info('>> CLIENT_TERMINATED_AND_ROUTER_CLEANED'),
        });
    }
};

const suspendClient = (client) => {
    router.post(route('dashboard.clients.suspend', client.id), {}, {
        onSuccess: () => toaster.info(`>> ${client.pppoe_username} SUSPENDED`),
        onError: () => toaster.error('>> ROUTER_UNREACHABLE: SUSPEND_FAILED'),
    });
};

const unsuspendClient = (client) => {
    router.post(route('dashboard.clients.unsuspend', client.id), {}, {
        onSuccess: () => toaster.info(`>> ${client.pppoe_username} REACTIVATED`),
        onError: () => toaster.error('>> ROUTER_UNREACHABLE: REACTIVATE_FAILED'),
    });
};

const columnHelper = createColumnHelper();
const columns = [
    columnHelper.accessor('full_name', {
        header: 'Client_Info',
        cell: (info) => h('div', [
            h('div', { class: 'font-bold text-ink uppercase' }, info.getValue()),
            h('div', { class: 'opacity-50 text-[9px]' }, info.row.original.phone_number),
        ]),
    }),
    columnHelper.accessor('pppoe_username', {
        header: 'Credentials',
        cell: (info) => h('div', [
            h('span', { class: 'bg-primary/10 px-2 py-0.5 rounded border border-primary/20' }, info.getValue()),
            h('div', { class: 'text-[9px] mt-1 opacity-40' }, info.row.original.package_name),
        ]),
    }),
    columnHelper.display({
        id: 'zone',
        header: 'Location_Zone',
        cell: (info) => h('div', [
            h('div', { class: 'uppercase' }, info.row.original.zone?.name || 'GENERIC'),
            h('div', { class: 'text-[9px] opacity-40 italic' }, info.row.original.sub_zone?.name || 'N/A'),
        ]),
    }),
    columnHelper.accessor('monthly_bill', {
        header: 'Billing_Status',
        cell: (info) => h('div', [
            h('div', { class: 'font-bold text-ink' }, `৳ ${info.getValue()}`),
            h('span', {
                class: `inline-block mt-1 px-1.5 py-0.5 border text-[9px] uppercase font-black ${statusStyles[info.row.original.effective_status] ?? ''}`,
            }, `[ ${info.row.original.effective_status} ]`),
        ]),
    }),
    columnHelper.accessor('expiry_date', {
        header: 'Expiry_Date',
        cell: (info) => h('span', {
            class: new Date(info.getValue()) < new Date() ? 'text-red-500 underline' : '',
        }, info.getValue()),
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: (info) => {
            const client = info.row.original;
            const actions = [
                h(Link, { href: route('dashboard.clients.edit', client.id), class: 'hover:text-ink' }, () => h(Edit3, { size: 16 })),
            ];
            if (client.effective_status === 'Suspended') {
                actions.push(h('button', { onClick: () => unsuspendClient(client), class: 'hover:text-primary', title: 'Reactivate' }, [h(PlayCircle, { size: 16 })]));
            } else {
                actions.push(h('button', { onClick: () => suspendClient(client), class: 'hover:text-yellow-500', title: 'Suspend' }, [h(PauseCircle, { size: 16 })]));
            }
            actions.push(h('button', { onClick: () => deleteClient(client.id, client.full_name), class: 'hover:text-red-500', title: 'Terminate' }, [h(Trash2, { size: 16 })]));

            return h('div', { class: 'flex justify-end gap-3 opacity-30 group-hover:opacity-100 transition' }, actions);
        },
    }),
];

const table = useVueTable({
    get data() { return props.clients.data; },
    columns,
    getCoreRowModel: getCoreRowModel(),
});

const tableContainer = ref(null);
const rowVirtualizer = useVirtualizer(computed(() => ({
    count: table.getRowModel().rows.length,
    getScrollElement: () => tableContainer.value,
    estimateSize: () => 64,
    overscan: 10,
})));

const sortableColumns = { full_name: 'Client_Info', pppoe_username: 'Credentials', monthly_bill: 'Billing_Status', expiry_date: 'Expiry_Date' };
</script>

<template>
    <Head title="CLIENT_DIRECTORY" />
    <div class="space-y-6 font-mono text-primary">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-primary/20 pb-6">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                    <Users :size="24" /> Subscriber_Database
                </h1>
                <p class="text-[9px] opacity-50">AUTHORIZED_ACCESS_ONLY // TOTAL_NODES: {{ clients.total }}</p>
            </div>

            <div class="flex gap-3">
                <Link :href="route('dashboard.clients.import.create')" class="border border-primary/40 text-primary px-6 py-2 text-[10px] font-black uppercase hover:bg-primary/10 transition flex items-center gap-2">
                    <UploadCloud :size="14" /> BULK_IMPORT
                </Link>
                <Link :href="route('dashboard.clients.create')" class="bg-primary text-black px-6 py-2 text-[10px] font-black uppercase hover:bg-white transition flex items-center gap-2">
                    <UserPlus :size="14" /> ADD_CLIENT
                </Link>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 opacity-50" :size="14" />
                <input v-model="search" type="text" placeholder="SEARCH_BY_NAME_PPPOE_OR_PHONE..."
                       class="w-full bg-surface border border-primary/30 py-2 pl-10 pr-4 text-[10px] outline-none focus:border-primary transition">
            </div>
            <select v-model="zoneId" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none focus:border-primary">
                <option value="">ALL_ZONES</option>
                <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
            </select>
            <select v-model="status" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none focus:border-primary">
                <option value="">ALL_STATUSES</option>
                <option value="Active">Active</option>
                <option value="Suspended">Suspended</option>
                <option value="Expired">Expired</option>
            </select>
            <select v-model="packageName" class="bg-surface border border-primary/30 py-2 px-3 text-[10px] outline-none focus:border-primary">
                <option value="">ALL_PACKAGES</option>
                <option v-for="p in packages" :key="p.name" :value="p.name">{{ p.name }}</option>
            </select>
        </div>

        <div class="border border-primary/20 bg-surface/80 shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[11px] border-collapse">
                    <thead class="bg-primary/10 border-b border-primary/20 uppercase font-black text-[9px] tracking-widest">
                    <tr v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <th v-for="header in headerGroup.headers" :key="header.id"
                            class="p-4"
                            :class="[header.column.id === 'actions' ? 'text-right' : '', sortableColumns[header.column.id] ? 'cursor-pointer select-none hover:text-ink' : '']"
                            @click="sortableColumns[header.column.id] && toggleSort(header.column.id)">
                            <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                            <span v-if="sort === header.column.id"> {{ direction === 'asc' ? '▲' : '▼' }}</span>
                        </th>
                    </tr>
                    </thead>
                </table>
            </div>

            <div ref="tableContainer" class="overflow-auto" style="max-height: 640px;">
                <table class="w-full text-left text-[11px] border-collapse">
                    <tbody class="divide-y divide-primary/10 relative" :style="{ height: `${rowVirtualizer.getTotalSize()}px` }">
                    <tr v-if="table.getRowModel().rows.length === 0">
                        <td :colspan="columns.length" class="p-8 text-center opacity-40 uppercase text-[10px]">NO_MATCHING_CLIENTS</td>
                    </tr>
                    <tr v-for="virtualRow in rowVirtualizer.getVirtualItems()" :key="table.getRowModel().rows[virtualRow.index].id"
                        class="hover:bg-primary/5 transition group absolute w-full"
                        :style="{ transform: `translateY(${virtualRow.start}px)`, height: `${virtualRow.size}px` }">
                        <td v-for="cell in table.getRowModel().rows[virtualRow.index].getVisibleCells()" :key="cell.id" class="p-4 align-top">
                            <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between items-center text-[10px] uppercase">
            <span class="opacity-50">
                Page {{ clients.current_page }} / {{ clients.last_page }} — Showing {{ clients.data.length }} of {{ clients.total }}
            </span>
            <div class="flex gap-2">
                <button :disabled="!clients.prev_page_url" @click="goToPage(clients.prev_page_url)"
                        class="border border-primary/30 px-3 py-1 flex items-center gap-1 disabled:opacity-30 hover:border-primary transition">
                    <ChevronLeft :size="12" /> PREV
                </button>
                <button :disabled="!clients.next_page_url" @click="goToPage(clients.next_page_url)"
                        class="border border-primary/30 px-3 py-1 flex items-center gap-1 disabled:opacity-30 hover:border-primary transition">
                    NEXT <ChevronRight :size="12" />
                </button>
            </div>
        </div>
    </div>
</template>
