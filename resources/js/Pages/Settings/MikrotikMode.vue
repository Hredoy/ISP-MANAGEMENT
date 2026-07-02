<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Settings2 } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

const props = defineProps({
    mode: { type: String, default: 'real' },
});

const page = usePage();

const form = useForm({
    mode: props.mode,
});

const submit = () => {
    form.post(route('dashboard.settings.mikrotik-mode.update'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Router Mode Settings" />

    <div class="space-y-6 font-mono text-primary">
        <div class="border-b border-primary/20 pb-6">
            <h1 class="flex items-center gap-2 text-2xl font-black uppercase italic tracking-tighter">
                <Settings2 :size="24" /> Router_Mode
            </h1>
            <p class="mt-2 text-[10px] uppercase tracking-widest text-primary/60">
                Global default for every MikroTik router that doesn't have its own mode override.
            </p>
        </div>

        <div v-if="page.props.flash?.message" class="border border-primary/30 bg-primary/10 p-4 text-[11px] font-black uppercase">
            {{ page.props.flash.message }}
        </div>

        <section class="max-w-md space-y-5 border border-primary/20 bg-surface p-5">
            <form @submit.prevent="submit" class="space-y-4">
                <label class="flex items-center gap-3 border border-primary/10 p-4 cursor-pointer hover:border-primary/40"
                       :class="{ 'border-primary bg-primary/10': form.mode === 'demo' }">
                    <input type="radio" value="demo" v-model="form.mode" />
                    <span>
                        <span class="block font-black uppercase text-ink">Demo</span>
                        <span class="block text-[9px] uppercase text-primary/50">Simulate every operation against the database. No real router connection.</span>
                    </span>
                </label>

                <label class="flex items-center gap-3 border border-primary/10 p-4 cursor-pointer hover:border-primary/40"
                       :class="{ 'border-primary bg-primary/10': form.mode === 'real' }">
                    <input type="radio" value="real" v-model="form.mode" />
                    <span>
                        <span class="block font-black uppercase text-ink">Real</span>
                        <span class="block text-[9px] uppercase text-primary/50">Connect to actual MikroTik RouterOS devices over the API.</span>
                    </span>
                </label>

                <button type="submit" class="inline-flex items-center gap-2 bg-primary px-5 py-3 text-[10px] font-black uppercase text-black hover:bg-white" :disabled="form.processing">
                    Save_Mode
                </button>
            </form>

            <p class="text-[9px] uppercase text-primary/50">
                Any router can override this via its own Mode setting (Demo / Real / Use_Global) on the MikroTik nodes page.
            </p>
        </section>
    </div>
</template>
