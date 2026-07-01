<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { createToaster } from '@meforma/vue-toaster';
import { Plug, Plus, Trash2, CheckCircle2, Send } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

defineOptions({ layout: ISPLayout });

const props = defineProps({
    smsGateways: { type: Array, default: () => [] },
    olts: { type: Array, default: () => [] },
});

const page = usePage();
const toaster = createToaster();

const providers = [
    { value: 'ssl_wireless', label: 'SSL Wireless' },
    { value: 'alpha_sms', label: 'Alpha SMS' },
    { value: 'twilio', label: 'Twilio' },
    { value: 'custom', label: 'Custom HTTP Gateway' },
];

const form = useForm({
    provider: 'ssl_wireless',
    display_name: '',
    credentials: {},
});

const testPhone = reactive({});

const isCustom = computed(() => form.provider === 'custom');
const isTwilio = computed(() => form.provider === 'twilio');
const isSsl = computed(() => form.provider === 'ssl_wireless');
const isAlpha = computed(() => form.provider === 'alpha_sms');

const submit = () => {
    form.post(route('dashboard.integrations.sms-gateways.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toaster.success('>> SMS_GATEWAY_ADDED');
        },
    });
};

const activate = (gateway) => {
    router.post(route('dashboard.integrations.sms-gateways.activate', gateway.id), {}, {
        preserveScroll: true,
        onSuccess: () => toaster.success(`>> ${gateway.display_name} IS_NOW_ACTIVE`),
    });
};

const destroyGateway = (gateway) => {
    if (confirm(`REMOVE_GATEWAY: ${gateway.display_name}?`)) {
        router.delete(route('dashboard.integrations.sms-gateways.destroy', gateway.id), { preserveScroll: true });
    }
};

const sendTest = async (gateway) => {
    const phone = testPhone[gateway.id];
    if (!phone) {
        toaster.error('>> ENTER_A_PHONE_NUMBER_FIRST');
        return;
    }

    try {
        const { data } = await axios.post(route('dashboard.integrations.sms-gateways.test', gateway.id), { phone });
        toaster.success(`>> ${data.message}`);
    } catch (error) {
        toaster.error(`>> ${error.response?.data?.message ?? 'TEST_SEND_FAILED'}`);
    }
};
</script>

<template>
    <Head title="Integrations" />

    <div class="space-y-6 font-mono text-primary">
        <div class="border-b border-primary/20 pb-6">
            <h1 class="flex items-center gap-2 text-2xl font-black uppercase italic tracking-tighter">
                <Plug :size="24" /> Integrations
            </h1>
            <p class="mt-2 text-[10px] uppercase tracking-widest text-primary/60">
                Configure SMS gateways and OLT devices used by the one-click provisioning wizard.
            </p>
        </div>

        <div v-if="page.props.flash?.message" class="border border-primary/30 bg-primary/10 p-4 text-[11px] font-black uppercase">
            {{ page.props.flash.message }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_.9fr]">
            <section class="space-y-4 border border-primary/20 bg-surface p-5">
                <h2 class="text-sm font-black uppercase text-ink">SMS_Gateways</h2>

                <div v-for="gateway in smsGateways" :key="gateway.id" class="border border-primary/10 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-black text-ink uppercase">{{ gateway.display_name }}</p>
                            <p class="text-[9px] uppercase text-primary/50">{{ gateway.provider }}</p>
                        </div>
                        <span v-if="gateway.is_active" class="flex items-center gap-1 text-[9px] font-black uppercase text-primary">
                            <CheckCircle2 :size="14" /> Active
                        </span>
                        <button v-else @click="activate(gateway)" class="border border-primary/30 px-3 py-1 text-[9px] font-black uppercase hover:bg-primary hover:text-black">
                            Set_Active
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <input v-model="testPhone[gateway.id]" type="text" placeholder="Test phone number"
                               class="flex-1 border-primary/30 bg-surface text-[10px] text-primary focus:border-primary focus:ring-primary" />
                        <button @click="sendTest(gateway)" class="border border-primary/30 p-2 hover:bg-primary hover:text-black" title="Send test SMS">
                            <Send :size="14" />
                        </button>
                        <button @click="destroyGateway(gateway)" class="border border-red-500/30 p-2 text-red-500 hover:bg-red-500 hover:text-black" title="Remove">
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>

                <p v-if="smsGateways.length === 0" class="text-[10px] uppercase text-primary/50">
                    No SMS gateways configured yet. Add one to enable the welcome-SMS provisioning step.
                </p>
            </section>

            <section class="space-y-5 border border-primary/20 bg-surface p-5">
                <h2 class="flex items-center gap-2 text-sm font-black uppercase text-ink">
                    <Plus :size="18" /> Add_SMS_Gateway
                </h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <label class="block text-[10px] font-bold uppercase text-primary/60">
                        Provider
                        <select v-model="form.provider" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary">
                            <option v-for="p in providers" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </label>
                    <label class="block text-[10px] font-bold uppercase text-primary/60">
                        Display_Name
                        <input v-model="form.display_name" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                    </label>

                    <template v-if="isSsl">
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            API_Token
                            <input v-model="form.credentials.api_token" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            SID
                            <input v-model="form.credentials.sid" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                    </template>

                    <template v-if="isAlpha">
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            API_Key
                            <input v-model="form.credentials.api_key" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            Sender_ID
                            <input v-model="form.credentials.sender_id" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                    </template>

                    <template v-if="isTwilio">
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            Account_SID
                            <input v-model="form.credentials.account_sid" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            Auth_Token
                            <input v-model="form.credentials.auth_token" type="password" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            From_Number
                            <input v-model="form.credentials.from_number" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                    </template>

                    <template v-if="isCustom">
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            Endpoint_URL
                            <input v-model="form.credentials.url" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary" />
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            Method
                            <select v-model="form.credentials.method" class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary">
                                <option value="post">POST</option>
                                <option value="get">GET</option>
                            </select>
                        </label>
                        <label class="block text-[10px] font-bold uppercase text-primary/60">
                            <span>Payload_Template (use </span><span v-pre>{{to}}</span><span> / </span><span v-pre>{{message}}</span><span> placeholders)</span>
                            <textarea v-model="form.credentials.payload_template" rows="3"
                                      class="mt-2 w-full border-primary/30 bg-surface text-primary focus:border-primary focus:ring-primary"></textarea>
                        </label>
                    </template>

                    <button type="submit" class="inline-flex items-center gap-2 bg-primary px-5 py-3 text-[10px] font-black uppercase text-black hover:bg-white" :disabled="form.processing">
                        <Plus :size="16" /> Add_Gateway
                    </button>
                </form>
            </section>
        </div>

        <section class="border border-primary/20 bg-surface p-5">
            <h2 class="mb-4 text-sm font-black uppercase text-ink">OLT_Devices</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="olt in olts" :key="olt.id" class="border border-primary/10 p-3 text-[10px]">
                    <p class="font-black text-ink uppercase">{{ olt.name }}</p>
                    <p class="text-primary/60 uppercase">{{ olt.vendor }} — {{ olt.is_active ? 'Active' : 'Inactive' }}</p>
                </div>
                <p v-if="olts.length === 0" class="text-[10px] uppercase text-primary/50">
                    No OLT devices configured yet. Add one under OLT_DEVICES to enable ONU binding.
                </p>
            </div>
        </section>
    </div>
</template>
