<script setup>
import { computed, reactive, ref } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { createToaster } from '@meforma/vue-toaster';
import { Save, ChevronLeft, RefreshCw, CheckCircle2, XCircle, Loader2, SkipForward, RotateCw } from 'lucide-vue-next';
import axios from 'axios';

defineOptions({ layout: ISPLayout });
const props = defineProps({
    routers: Array,
    zones: Array,
    subZones: Array,
    packages: Array, // [{ name, price, mikrotik_id }]
    olts: { type: Array, default: () => [] },
});

const toaster = createToaster();

const expiryDate = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() + 30);
    return d.toISOString().split('T')[0];
});

const form = reactive({
    mikrotik_id: '',
    package_name: '',
    zone_id: '',
    sub_zone_id: '',
    pppoe_username: '',
    pppoe_password: '',
    full_name: '',
    email: '',
    phone_number: '',
    telegram_chat_id: '',
    monthly_bill: 500,
    full_address: '',
    expiry_date: expiryDate.value,
    additional_notes: '',
    olt_id: '',
    onu_mac: '',
    onu_serial: '',
    pon_port: '',
});

const availablePackages = computed(() => {
    if (!form.mikrotik_id) return props.packages;
    return props.packages.filter(p => p.mikrotik_id === form.mikrotik_id);
});

const onPackageChange = () => {
    const selected = props.packages.find(p => p.name === form.package_name);
    if (selected) form.monthly_bill = selected.price;
};

const generatePassword = () => {
    const chars = "abcdefghijklmnopqrstuvwxyz1234567890";
    let pass = "";
    for (let i = 0; i < 8; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
    form.pppoe_password = pass;
};

const STEP_DEFS = [
    { key: 'client', label: '01. Create client record' },
    { key: 'pppoe', label: '02. Provision PPPoE user' },
    { key: 'onu', label: '03. Bind ONU in OLT' },
    { key: 'queue', label: '04. Apply bandwidth queue' },
    { key: 'expiry', label: '05. Set expiry date' },
    { key: 'sms', label: '06. Send welcome SMS' },
];

const steps = reactive(STEP_DEFS.map(s => ({ ...s, status: 'pending', message: '' })));
const submitting = ref(false);
const clientId = ref(null);
const errors = reactive({});

const resetSteps = () => {
    steps.forEach(s => { s.status = 'pending'; s.message = ''; });
    clientId.value = null;
};

const setStep = (key, status, message = '') => {
    const step = steps.find(s => s.key === key);
    step.status = status;
    step.message = message;
};

const runClientStep = async () => {
    setStep('client', 'running');
    try {
        const { data } = await axios.post(route('dashboard.clients.provision.client'), form);
        clientId.value = data.client_id;
        setStep('client', 'success', data.message);
        return true;
    } catch (error) {
        Object.assign(errors, error.response?.data?.errors ?? {});
        setStep('client', 'error', error.response?.data?.message ?? 'Failed to create client record.');
        return false;
    }
};

const stepRunners = {
    pppoe: () => axios.post(route('dashboard.clients.provision.pppoe', clientId.value)),
    onu: () => axios.post(route('dashboard.clients.provision.onu', clientId.value), {
        olt_id: form.olt_id || null,
        onu_mac: form.onu_mac || null,
        onu_serial: form.onu_serial || null,
        pon_port: form.pon_port || null,
    }),
    queue: () => axios.post(route('dashboard.clients.provision.queue', clientId.value)),
    expiry: () => axios.post(route('dashboard.clients.provision.expiry', clientId.value)),
    sms: () => axios.post(route('dashboard.clients.provision.sms', clientId.value)),
};

const runStep = async (key) => {
    setStep(key, 'running');
    try {
        const { data } = await stepRunners[key]();
        setStep(key, data.skipped ? 'skipped' : 'success', data.message);
        return true;
    } catch (error) {
        setStep(key, 'error', error.response?.data?.message ?? 'Step failed.');
        return false;
    }
};

const retryStep = async (key) => {
    if (key === 'client') {
        await runClientStep();
        return;
    }
    await runStep(key);
};

const allDone = computed(() => steps.every(s => s.status === 'success' || s.status === 'skipped'));

const submit = async () => {
    submitting.value = true;
    resetSteps();
    Object.keys(errors).forEach(k => delete errors[k]);

    const created = await runClientStep();
    if (!created) {
        submitting.value = false;
        return;
    }

    for (const key of ['pppoe', 'onu', 'queue', 'expiry', 'sms']) {
        await runStep(key);
    }

    submitting.value = false;

    if (allDone.value) {
        toaster.success('>> CLIENT_PROVISIONED_SUCCESSFULLY');
        setTimeout(() => router.visit(route('dashboard.clients.index')), 1200);
    }
};
</script>

<template>
    <Head title="PROVISION_NEW_CLIENT" />
    <div class="max-w-5xl mx-auto font-mono text-primary">

        <div class="flex justify-between items-center mb-6">
            <Link :href="route('dashboard.clients.index')" class="flex items-center gap-2 text-[10px] uppercase opacity-60 hover:opacity-100 transition">
                <ChevronLeft :size="14" /> BACK_TO_LIST
            </Link>
            <h2 class="text-xl font-black uppercase italic tracking-tighter">Initialize_New_Protocol</h2>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="border border-primary/20 bg-surface/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2 mb-4">01. Connection_Logic</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Router *</label>
                        <select v-model="form.mikrotik_id" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Router --</option>
                            <option v-for="r in routers" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Package *</label>
                        <select v-model="form.package_name" @change="onPackageChange" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Package --</option>
                            <option v-for="p in availablePackages" :key="p.name" :value="p.name">{{ p.name }} — ৳{{ p.price }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select OLT (Optional)</label>
                        <select v-model="form.olt_id" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- No OLT (Generic) --</option>
                            <option v-for="o in olts" :key="o.id" :value="o.id">{{ o.name }} ({{ o.vendor }})</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Zone</label>
                        <select v-model="form.zone_id" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Zone --</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Sub Zone</label>
                        <select v-model="form.sub_zone_id" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Sub Zone --</option>
                            <option v-for="sz in subZones" :key="sz.id" :value="sz.id">{{ sz.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="form.olt_id" class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-primary/10">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">ONU Serial</label>
                        <input v-model="form.onu_serial" type="text" placeholder="e.g. HWTC12345678" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">ONU MAC (if no serial)</label>
                        <input v-model="form.onu_mac" type="text" placeholder="AA:BB:CC:DD:EE:FF" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PON Port</label>
                        <input v-model="form.pon_port" type="text" placeholder="e.g. 0/1" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PPPoE Username *</label>
                        <input v-model="form.pppoe_username" type="text" placeholder="e.g., user123" class="w-full bg-surface border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                        <p class="text-[8px] opacity-40 italic">This will be used for PPPoE login</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PPPoE Password *</label>
                        <div class="flex gap-2">
                            <input v-model="form.pppoe_password" type="text" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                            <button type="button" @click="generatePassword" class="bg-primary/10 border border-primary/30 px-3 text-[10px] hover:bg-primary hover:text-black transition flex items-center gap-1">
                                <RefreshCw :size="12" /> GENERATE
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-primary/20 bg-surface/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2">02. Personal_Data</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Full Name *</label>
                        <input v-model="form.full_name" type="text" placeholder="Enter client full name" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Email Address</label>
                        <input v-model="form.email" type="email" placeholder="client@example.com" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Phone Number *</label>
                        <input v-model="form.phone_number" type="text" placeholder="01XXXXXXXXX" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Telegram Chat ID</label>
                        <input v-model="form.telegram_chat_id" type="text" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="col-span-2 space-y-1">
                        <label class="text-[9px] uppercase font-bold">Full Address *</label>
                        <textarea v-model="form.full_address" rows="2" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="border border-primary/20 bg-surface/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2">03. Financial_Schedule</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Monthly Bill (৳) *</label>
                        <input v-model="form.monthly_bill" type="number" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none text-ink font-bold">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Expires</label>
                        <input :value="form.expiry_date" type="text" disabled class="w-full bg-surface/60 border border-primary/20 p-2 text-xs outline-none opacity-60 cursor-not-allowed">
                        <p class="text-[8px] opacity-40 italic">Auto-set to today + 30 days</p>
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] uppercase font-bold">Additional Notes</label>
                    <textarea v-model="form.additional_notes" rows="2" class="w-full bg-surface border border-primary/30 p-2 text-xs outline-none"></textarea>
                </div>
            </div>

            <div v-if="Object.keys(errors).length" class="border border-red-500/40 bg-red-500/10 p-4 text-[10px] uppercase text-red-400 space-y-1">
                <p v-for="(msg, field) in errors" :key="field">{{ field }}: {{ Array.isArray(msg) ? msg[0] : msg }}</p>
            </div>

            <div class="border border-primary/20 bg-surface/60 p-6 space-y-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2 mb-2">Provisioning_Steps</h3>
                <div v-for="step in steps" :key="step.key" class="flex items-center justify-between gap-4 py-1 text-xs">
                    <div class="flex items-center gap-2">
                        <Loader2 v-if="step.status === 'running'" :size="14" class="animate-spin text-primary" />
                        <CheckCircle2 v-else-if="step.status === 'success'" :size="14" class="text-primary" />
                        <SkipForward v-else-if="step.status === 'skipped'" :size="14" class="text-primary/50" />
                        <XCircle v-else-if="step.status === 'error'" :size="14" class="text-red-500" />
                        <div v-else class="w-[14px] h-[14px] rounded-full border border-primary/30"></div>
                        <span :class="step.status === 'error' ? 'text-red-400' : 'text-primary'">{{ step.label }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[9px] opacity-50 italic max-w-xs truncate">{{ step.message }}</span>
                        <button v-if="step.status === 'error'" type="button" @click="retryStep(step.key)"
                                class="flex items-center gap-1 border border-red-500/40 text-red-400 px-2 py-1 text-[9px] uppercase hover:bg-red-500 hover:text-black transition">
                            <RotateCw :size="10" /> Retry
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 pb-10">
                <button :disabled="submitting" type="submit" class="bg-primary text-black font-black px-10 py-4 uppercase text-xs flex items-center gap-2 hover:bg-white transition disabled:opacity-50">
                    <Save :size="18" /> {{ submitting ? 'PROVISIONING_USER...' : 'CREATE_CLIENT_NODE' }}
                </button>
                <Link :href="route('dashboard.clients.index')" class="text-[10px] uppercase underline opacity-60 hover:opacity-100 transition">CANCEL_TRANSACTION</Link>
            </div>
        </form>
    </div>
</template>
