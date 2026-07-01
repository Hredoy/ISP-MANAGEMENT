<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

defineOptions({ layout: ISPLayout });

const props = defineProps({
    olt: Object
});

const form = useForm({
    name: props.olt.name,
    vendor: props.olt.vendor,
    host: props.olt.host,
    port: props.olt.port,
    username: props.olt.username ?? '',
    password: props.olt.password ?? '',
    snmp_community: props.olt.snmp_community ?? '',
    is_active: !!props.olt.is_active,
});

const isSnmp = computed(() => form.vendor === 'vsol');

const submit = () => {
    form.put(route('dashboard.olts.update', props.olt.id));
};
</script>

<template>
    <Head :title="'EDIT: ' + olt.name" />

    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <Link :href="route('dashboard.olts.index')" class="flex items-center text-primary/60 hover:text-primary transition">
                <ChevronLeft :size="16" />
                <span class="text-[10px] ml-1 uppercase underline">Back_To_OLTs</span>
            </Link>
            <div class="text-right">
                <p class="text-[10px] opacity-50">OLT_ID</p>
                <p class="text-xs font-bold text-primary">#{{ olt.id }}</p>
            </div>
        </div>

        <div class="border border-primary/30 bg-black/80 p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 border-t-2 border-r-2 border-primary/20"></div>

            <div class="flex items-center gap-3 mb-8">
                <div class="p-2 bg-primary/10 border border-primary/30 text-primary">
                    <ShieldAlert :size="20" />
                </div>
                <h2 class="text-xl font-black text-primary uppercase tracking-tighter">
                    Modify_OLT_Credentials
                </h2>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Identifier</label>
                    <input v-model="form.name" type="text"
                           class="w-full bg-black border border-primary/20 p-3 text-primary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all">
                </div>

                <div>
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Vendor</label>
                    <select v-model="form.vendor" class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                        <option value="huawei">Huawei</option>
                        <option value="zte">ZTE</option>
                        <option value="vsol">VSOL (SNMP)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">IPv4_Address</label>
                    <input v-model="form.host" type="text"
                           class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                </div>

                <div>
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Port</label>
                    <input v-model="form.port" type="number"
                           class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                </div>
                <div class="flex items-end gap-2 pb-3">
                    <input v-model="form.is_active" type="checkbox" id="is_active" class="accent-primary">
                    <label for="is_active" class="text-[10px] text-primary/60 uppercase tracking-widest">Active</label>
                </div>

                <template v-if="!isSnmp">
                    <div>
                        <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Login_User</label>
                        <input v-model="form.username" type="text"
                               class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Auth_Key</label>
                        <input v-model="form.password" type="password"
                               class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                    </div>
                </template>
                <template v-else>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">SNMP_Community</label>
                        <input v-model="form.snmp_community" type="text"
                               class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                    </div>
                </template>

                <div class="md:col-span-2 pt-4">
                    <button :disabled="form.processing"
                            class="group w-full bg-primary hover:bg-white text-black font-black py-4 uppercase text-xs transition-all flex items-center justify-center gap-2">
                        <Save :size="16" v-if="!form.processing" />
                        <span>{{ form.processing ? 'RE-SYNCING_PROTOCOLS...' : 'UPDATE_CONFIG_FILE' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
