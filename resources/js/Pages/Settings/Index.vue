<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Save, Settings } from 'lucide-vue-next';
import { createToaster } from '@meforma/vue-toaster';

defineOptions({ layout: ISPLayout });

const toaster = createToaster();
const props = defineProps({
    organization: Object,
    billing: Object,
    notifications: Object,
});

const form = useForm({
    organization: {
        name: props.organization.name ?? '',
        phone: props.organization.phone ?? '',
        email: props.organization.email ?? '',
        address: props.organization.address ?? '',
    },
    billing: {
        billing_day: props.billing.billing_day ?? 1,
        currency: props.billing.currency ?? 'BDT',
        payment_methods: props.billing.payment_methods ?? ['cash', 'bkash', 'nagad', 'bank'],
    },
    notifications: {
        sms_enabled: props.notifications.sms_enabled ?? true,
        payment_confirmation: props.notifications.payment_confirmation ?? true,
        expiry_reminder: props.notifications.expiry_reminder ?? true,
    },
});

const methodText = computed({
    get() {
        return form.billing.payment_methods.join(', ');
    },
    set(value) {
        form.billing.payment_methods = value.split(',').map((item) => item.trim()).filter(Boolean);
    },
});

const submit = () => {
    form.patch(route('dashboard.settings.update'), {
        preserveScroll: true,
        onSuccess: () => toaster.info('>> TENANT_SETTINGS_UPDATED'),
    });
};
</script>

<template>
    <Head title="SETTINGS" />

    <form @submit.prevent="submit" class="space-y-6 font-mono text-primary max-w-5xl">
        <div class="border-b border-primary/20 pb-6">
            <h1 class="text-2xl font-black uppercase italic tracking-tighter flex items-center gap-2">
                <Settings :size="24" /> Tenant_Settings
            </h1>
            <p class="text-[9px] opacity-50">ORGANIZATION // BILLING // NOTIFICATIONS</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <section class="border border-primary/20 bg-surface p-4 space-y-4">
                <h2 class="text-[11px] font-black uppercase">Organization</h2>
                <label class="block text-[10px] uppercase">Name
                    <input v-model="form.organization.name" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                </label>
                <label class="block text-[10px] uppercase">Phone
                    <input v-model="form.organization.phone" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                </label>
                <label class="block text-[10px] uppercase">Email
                    <input v-model="form.organization.email" type="email" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                </label>
                <label class="block text-[10px] uppercase">Address
                    <textarea v-model="form.organization.address" rows="3" class="mt-1 w-full bg-black/20 border border-primary/30 p-2"></textarea>
                </label>
            </section>

            <section class="border border-primary/20 bg-surface p-4 space-y-4">
                <h2 class="text-[11px] font-black uppercase">Billing</h2>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-[10px] uppercase">Billing_Day
                        <input v-model="form.billing.billing_day" type="number" min="1" max="28" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                    <label class="block text-[10px] uppercase">Currency
                        <input v-model="form.billing.currency" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                    </label>
                </div>
                <label class="block text-[10px] uppercase">Payment_Methods
                    <input v-model="methodText.value" class="mt-1 w-full bg-black/20 border border-primary/30 p-2">
                </label>

                <h2 class="text-[11px] font-black uppercase pt-2">Notifications</h2>
                <label class="flex items-center gap-2 text-[10px] uppercase">
                    <input v-model="form.notifications.sms_enabled" type="checkbox" class="bg-black/20 border-primary/40"> SMS_Enabled
                </label>
                <label class="flex items-center gap-2 text-[10px] uppercase">
                    <input v-model="form.notifications.payment_confirmation" type="checkbox" class="bg-black/20 border-primary/40"> Payment_Confirmation
                </label>
                <label class="flex items-center gap-2 text-[10px] uppercase">
                    <input v-model="form.notifications.expiry_reminder" type="checkbox" class="bg-black/20 border-primary/40"> Expiry_Reminder
                </label>
            </section>
        </div>

        <div v-if="Object.keys(form.errors).length" class="text-red-400 text-[10px] space-y-1">
            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
        </div>

        <button type="submit" :disabled="form.processing" class="bg-primary text-black px-6 py-2 text-[10px] font-black uppercase hover:bg-white transition flex items-center gap-2 disabled:opacity-50">
            <Save :size="14" /> Save_Settings
        </button>
    </form>
</template>
