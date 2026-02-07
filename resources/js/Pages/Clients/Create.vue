<script setup>
import { ref } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, RefreshCw, ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({
    routers: Array,
    zones: Array,
    subZones: Array,
    packages: Array // Assuming you have a list of MikroTik profiles
});

const form = useForm({
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
    expiry_date: new Date().toISOString().split('T')[0],
    status: 'Inactive',
    additional_notes: ''
});

const generatePassword = () => {
    const chars = "abcdefghijklmnopqrstuvwxyz1234567890";
    let pass = "";
    for (let i = 0; i < 8; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
    form.pppoe_password = pass;
};

const submit = () => {
    form.post(route('dashboard.clients.store'));
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
            <div class="border border-primary/20 bg-black/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2 mb-4">01. Connection_Logic</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Router *</label>
                        <select v-model="form.mikrotik_id" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Router --</option>
                            <option v-for="r in routers" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Package *</label>
                        <select v-model="form.package_name" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Package --</option>
                            <option v-for="p in packages" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </div>
                    <div class="space-y-1 opacity-30 cursor-not-allowed">
                        <label class="text-[9px] uppercase font-bold">Select OLT (Optional)</label>
                        <select disabled class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                            <option>-- No OLT (Generic) --</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Zone</label>
                        <select v-model="form.zone_id" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Zone --</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">Select Sub Zone</label>
                        <select v-model="form.sub_zone_id" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                            <option value="">-- Select Sub Zone --</option>
                            <option v-for="sz in subZones" :key="sz.id" :value="sz.id">{{ sz.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PPPoE Username *</label>
                        <input v-model="form.pppoe_username" type="text" placeholder="e.g., user123" class="w-full bg-black border border-primary/30 p-2 text-xs focus:border-primary outline-none">
                        <p class="text-[8px] opacity-40 italic">This will be used for PPPoE login</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold opacity-70">PPPoE Password *</label>
                        <div class="flex gap-2">
                            <input v-model="form.pppoe_password" type="text" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                            <button type="button" @click="generatePassword" class="bg-primary/10 border border-primary/30 px-3 text-[10px] hover:bg-primary hover:text-black transition flex items-center gap-1">
                                <RefreshCw :size="12" /> GENERATE
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border border-primary/20 bg-black/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2">02. Personal_Data</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Full Name *</label>
                        <input v-model="form.full_name" type="text" placeholder="Enter client full name" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Email Address</label>
                        <input v-model="form.email" type="email" placeholder="client@example.com" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Phone Number *</label>
                        <input v-model="form.phone_number" type="text" placeholder="01XXXXXXXXX" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Telegram Chat ID</label>
                        <input v-model="form.telegram_chat_id" type="text" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="col-span-2 space-y-1">
                        <label class="text-[9px] uppercase font-bold">Full Address *</label>
                        <textarea v-model="form.full_address" rows="2" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="border border-primary/20 bg-black/60 p-6 space-y-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] border-b border-primary/10 pb-2">03. Financial_Schedule</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Monthly Bill (৳) *</label>
                        <input v-model="form.monthly_bill" type="number" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none text-white font-bold">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Expiry Date *</label>
                        <input v-model="form.expiry_date" type="date" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] uppercase font-bold">Status *</label>
                        <select v-model="form.status" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] uppercase font-bold">Additional Notes</label>
                    <textarea v-model="form.additional_notes" rows="2" class="w-full bg-black border border-primary/30 p-2 text-xs outline-none"></textarea>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 pb-10">
                <button :disabled="form.processing" type="submit" class="bg-primary text-black font-black px-10 py-4 uppercase text-xs flex items-center gap-2 hover:bg-white transition disabled:opacity-50">
                    <Save :size="18" /> {{ form.processing ? 'PROVISIONING_USER...' : 'CREATE_CLIENT_NODE' }}
                </button>
                <Link :href="route('dashboard.clients.index')" class="text-[10px] uppercase underline opacity-60 hover:opacity-100 transition">CANCEL_TRANSACTION</Link>
            </div>
        </form>
    </div>
</template>
