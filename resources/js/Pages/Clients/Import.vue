<script setup>
import { ref } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { UploadCloud, Download, ChevronLeft, Loader2, CheckCircle2, XCircle, AlertTriangle } from 'lucide-vue-next';
import axios from 'axios';

defineOptions({ layout: ISPLayout });

const fileInput = ref(null);
const selectedFile = ref(null);
const stage = ref('idle'); // idle | previewing | previewed | importing | imported
const result = ref(null);
const errorMessage = ref('');

const onFileChange = (event) => {
    selectedFile.value = event.target.files[0] || null;
    stage.value = 'idle';
    result.value = null;
    errorMessage.value = '';
};

const submit = async (endpoint, nextStage) => {
    if (!selectedFile.value) return;

    const form = new FormData();
    form.append('file', selectedFile.value);

    stage.value = endpoint === 'preview' ? 'previewing' : 'importing';
    errorMessage.value = '';

    try {
        const { data } = await axios.post(route(`dashboard.clients.import.${endpoint}`), form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        result.value = data;
        stage.value = nextStage;
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Import failed - check the file and try again.';
        stage.value = 'idle';
    }
};

const preview = () => submit('preview', 'previewed');
const confirmImport = () => submit('store', 'imported');

const statusColor = (status) => ({
    created: 'text-green-500',
    valid: 'text-primary',
    invalid: 'text-red-500',
    failed: 'text-red-500',
}[status] || 'text-primary/60');
</script>

<template>
    <Head title="BULK_CLIENT_IMPORT" />
    <div class="space-y-6 font-mono text-primary max-w-4xl">
        <div class="flex items-center justify-between border-b border-primary/20 pb-6">
            <div>
                <h1 class="text-2xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                    <UploadCloud :size="24" /> Bulk_Client_Import
                </h1>
                <p class="text-[9px] opacity-50">UPLOAD_EXCEL // PREVIEW // CONFIRM</p>
            </div>
            <Link :href="route('dashboard.clients.index')" class="border border-primary/30 px-4 py-2 text-[10px] font-black uppercase hover:bg-primary/10 transition flex items-center gap-2">
                <ChevronLeft :size="14" /> BACK
            </Link>
        </div>

        <div class="border border-primary/30 bg-surface/50 p-6 space-y-4">
            <a :href="route('dashboard.clients.import.template')" class="inline-flex items-center gap-2 text-[10px] uppercase text-primary/80 hover:text-primary underline">
                <Download :size="14" /> Download_Import_Template (.xlsx)
            </a>

            <p class="text-[10px] opacity-60 leading-relaxed">
                Columns: Name, Phone, Address, Package (must match an existing package name exactly),
                PPPoE Username, PPPoE Password (optional - auto-generated if blank), ONU MAC (optional),
                Zone (optional - created automatically if it doesn't exist yet).
            </p>

            <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" @change="onFileChange"
                   class="block w-full text-[10px] file:mr-4 file:py-2 file:px-4 file:border-0 file:bg-primary file:text-black file:font-black file:uppercase file:text-[10px] bg-surface border border-primary/30 text-primary" />

            <div class="flex gap-3">
                <button :disabled="!selectedFile || stage === 'previewing'" @click="preview"
                        class="bg-primary text-black px-6 py-2 text-[10px] font-black uppercase hover:bg-white transition disabled:opacity-40 flex items-center gap-2">
                    <Loader2 v-if="stage === 'previewing'" :size="14" class="animate-spin" />
                    Preview_Rows
                </button>
                <button v-if="stage === 'previewed'" :disabled="stage === 'importing'" @click="confirmImport"
                        class="border border-primary text-primary px-6 py-2 text-[10px] font-black uppercase hover:bg-primary/10 transition disabled:opacity-40 flex items-center gap-2">
                    <Loader2 v-if="stage === 'importing'" :size="14" class="animate-spin" />
                    Confirm_Import
                </button>
            </div>

            <p v-if="errorMessage" class="text-red-500 text-[10px] flex items-center gap-2">
                <AlertTriangle :size="14" /> {{ errorMessage }}
            </p>
        </div>

        <div v-if="result" class="border border-primary/30 bg-surface/50 p-6 space-y-4">
            <h2 class="text-xs font-black uppercase tracking-widest">
                {{ stage === 'imported' ? 'Import_Result' : 'Preview_Result' }}
            </h2>
            <div class="flex gap-6 text-[10px] uppercase">
                <span>Total: {{ result.summary.total }}</span>
                <span v-if="stage === 'imported'" class="text-green-500">Created: {{ result.summary.created }}</span>
                <span v-else class="text-primary">Valid: {{ result.summary.valid }}</span>
                <span class="text-red-500">Invalid: {{ result.summary.invalid }}</span>
                <span v-if="result.summary.failed" class="text-red-500">Failed: {{ result.summary.failed }}</span>
            </div>

            <div class="border border-primary/20 overflow-hidden max-h-96 overflow-y-auto">
                <table class="w-full text-[11px] text-left">
                    <thead class="bg-primary/10 text-primary border-b border-primary/20 sticky top-0">
                    <tr>
                        <th class="p-3">ROW</th>
                        <th class="p-3">NAME</th>
                        <th class="p-3">PHONE</th>
                        <th class="p-3">STATUS</th>
                        <th class="p-3">NOTE</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                    <tr v-for="row in result.rows" :key="row.row" class="hover:bg-primary/5">
                        <td class="p-3">{{ row.row }}</td>
                        <td class="p-3">{{ row.name }}</td>
                        <td class="p-3">{{ row.phone }}</td>
                        <td class="p-3 font-bold uppercase" :class="statusColor(row.status)">
                            <CheckCircle2 v-if="row.status === 'created' || row.status === 'valid'" :size="12" class="inline mr-1" />
                            <XCircle v-else :size="12" class="inline mr-1" />
                            {{ row.status }}
                        </td>
                        <td class="p-3 opacity-70">{{ row.reason || '-' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
