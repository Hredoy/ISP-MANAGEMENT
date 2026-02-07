<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { useForm } from '@inertiajs/vue3';

defineOptions({ layout: ISPLayout });

const form = useForm({
    name: '', host: '', username: '', password: '', port: 8728
});

const submit = () => form.post('/dashboard/mikrotik');
</script>

<template>
    <div class="max-w-2xl mx-auto border border-primary/30 p-8 bg-black">
        <h2 class="text-primary font-bold mb-6 text-center uppercase tracking-widest underline">Configure_New_Node</h2>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-[10px] mb-1">NODE_NAME</label>
                <input v-model="form.name" type="text" class="w-full bg-black border border-primary/40 p-2 text-primary focus:border-primary outline-none">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] mb-1">HOST_IP</label>
                    <input v-model="form.host" type="text" class="w-full bg-black border border-primary/40 p-2 text-primary outline-none">
                </div>
                <div>
                    <label class="block text-[10px] mb-1">API_PORT</label>
                    <input v-model="form.port" type="number" class="w-full bg-black border border-primary/40 p-2 text-primary outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] mb-1">USER_ID</label>
                    <input v-model="form.username" type="text" class="w-full bg-black border border-primary/40 p-2 text-primary outline-none">
                </div>
                <div>
                    <label class="block text-[10px] mb-1">PASS_KEY</label>
                    <input v-model="form.password" type="password" class="w-full bg-black border border-primary/40 p-2 text-primary outline-none">
                </div>
            </div>

            <button :disabled="form.processing" class="w-full bg-primary text-black font-bold py-3 uppercase text-xs mt-4">
                {{ form.processing ? 'SYNCING...' : 'ESTABLISH_CONNECTION' }}
            </button>
        </form>
    </div>
</template>
