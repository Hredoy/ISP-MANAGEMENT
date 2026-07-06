<script setup>
import { Link } from '@inertiajs/vue3';
import { UserPlus, Users, MessageSquareText } from 'lucide-vue-next';
</script>

<template>
    <div class="border border-primary/20 bg-surface p-4">
        <h3 class="text-[10px] font-bold text-primary underline uppercase tracking-widest mb-3">Quick_Actions</h3>
        <div class="flex flex-wrap gap-3">
            <Link :href="route('dashboard.clients.create')" prefetch
                  class="flex items-center gap-2 px-3 py-2 border border-primary/40 text-primary text-[11px] font-bold uppercase hover:bg-primary/10 transition-all">
                <UserPlus :size="14" /> Add_Client
            </Link>
            <Link :href="route('dashboard.clients.index')" prefetch
                  class="flex items-center gap-2 px-3 py-2 border border-primary/40 text-primary text-[11px] font-bold uppercase hover:bg-primary/10 transition-all">
                <Users :size="14" /> Collect_Payment
            </Link>
            <Link :href="route('dashboard.integrations.index')" prefetch
                  class="flex items-center gap-2 px-3 py-2 border border-primary/40 text-primary text-[11px] font-bold uppercase hover:bg-primary/10 transition-all">
                <MessageSquareText :size="14" /> Send_Notice
            </Link>
        </div>
    </div>
</template>
