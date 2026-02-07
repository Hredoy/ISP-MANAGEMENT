<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Save, ChevronLeft, ShieldAlert } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

const props = defineProps({
    router: Object
});

// Initialize form with existing data
const form = useForm({
    name: props.router.name,
    host: props.router.host,
    port: props.router.port,
    username: props.router.username,
    password: props.router.password,
});

const submit = () => {
    form.put(route('dashboard.mikrotik.update', props.router.id));
};
</script>

<template>
    <Head :title="'EDIT: ' + router.name" />

    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <Link :href="route('dashboard.mikrotik.index')" class="flex items-center text-primary/60 hover:text-primary transition">
                <ChevronLeft :size="16" />
                <span class="text-[10px] ml-1 uppercase underline">Back_To_Nodes</span>
            </Link>
            <div class="text-right">
                <p class="text-[10px] opacity-50">NODE_ID</p>
                <p class="text-xs font-bold text-primary">#{{ router.id }}</p>
            </div>
        </div>

        <div class="border border-primary/30 bg-black/80 p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 border-t-2 border-r-2 border-primary/20"></div>

            <div class="flex items-center gap-3 mb-8">
                <div class="p-2 bg-primary/10 border border-primary/30 text-primary">
                    <ShieldAlert :size="20" />
                </div>
                <h2 class="text-xl font-black text-primary uppercase tracking-tighter">
                    Modify_Node_Credentials
                </h2>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">Identifier</label>
                    <input v-model="form.name" type="text"
                           class="w-full bg-black border border-primary/20 p-3 text-primary focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all"
                           placeholder="e.g. CORE_ROUTER_01">
                </div>

                <div>
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">IPv4_Address</label>
                    <input v-model="form.host" type="text"
                           class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                </div>
                <div>
                    <label class="block text-[10px] text-primary/60 mb-2 uppercase tracking-widest">API_Port</label>
                    <input v-model="form.port" type="number"
                           class="w-full bg-black border border-primary/20 p-3 text-primary outline-none">
                </div>

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
