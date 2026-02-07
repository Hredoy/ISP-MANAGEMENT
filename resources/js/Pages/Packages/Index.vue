<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, Box, Plus } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ packages: Array });
</script>

<template>
    <Head title="PACKAGE_LIST" />
    <div class="space-y-6 font-mono text-primary">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-black uppercase tracking-tighter italic flex items-center gap-2">
                <Box :size="20" /> Service_Packages
            </h1>
            <Link :href="route('dashboard.packages.create')" class="bg-primary text-black px-4 py-2 text-[10px] font-black uppercase hover:bg-white transition">
                + NEW_PACKAGE
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="pkg in packages" :key="pkg.id" class="border border-primary/20 bg-black/80 p-6 relative group overflow-hidden">
                <div class="absolute top-0 right-0 p-2 opacity-20 group-hover:opacity-100 transition">
                    <button @click="router.delete(route('dashboard.packages.destroy', pkg.id))" class="text-red-500">
                        <Trash2 :size="16" />
                    </button>
                </div>
                <h3 class="text-white font-bold text-lg mb-1 uppercase">{{ pkg.name }}</h3>
                <p class="text-[10px] text-primary/50 mb-4 tracking-widest border-b border-primary/10 pb-2">ROUTER: {{ pkg.mikrotik.name }}</p>

                <div class="space-y-2 text-[11px]">
                    <div class="flex justify-between">
                        <span class="opacity-60 uppercase">Speed:</span>
                        <span class="font-bold text-white">{{ pkg.rate_limit }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-60 uppercase">Price:</span>
                        <span class="font-bold text-primary">৳ {{ pkg.price }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
