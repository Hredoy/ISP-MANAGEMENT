<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Zap } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

defineOptions({ layout: ISPLayout });
const props = defineProps({ router: Object, sessions: Array });

const base = `/dashboard/mikrotik/${props.router.id}/pppoe-sessions`;
const selected = reactive({});

const selectedUsernames = computed(() => Object.entries(selected).filter(([, checked]) => checked).map(([name]) => name));
const allChecked = computed(() => props.sessions.length > 0 && props.sessions.every((s) => selected[s.name]));

const toggleAll = () => {
    const next = !allChecked.value;
    props.sessions.forEach((s) => { selected[s.name] = next; });
};

const kill = (session) => {
    if (confirm(`FORCE_DISCONNECT '${session.name}'?`)) {
        router.delete(`${base}/${encodeURIComponent(session.name)}`, { preserveScroll: true });
    }
};

const bulkKill = () => {
    if (!selectedUsernames.value.length) return;
    if (confirm(`FORCE_DISCONNECT ${selectedUsernames.value.length} SESSION(S)?`)) {
        router.post(`${base}/bulk-kill`, { usernames: selectedUsernames.value }, {
            preserveScroll: true,
            onSuccess: () => Object.keys(selected).forEach((k) => delete selected[k]),
        });
    }
};
</script>

<template>
    <Head :title="`${router.name} - PPPoE Sessions`" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> ACTIVE_PPPOE_SESSIONS :: {{ router.name }}</h1>
            <Link href="/dashboard/mikrotik" class="text-[10px] text-primary/60 hover:text-primary uppercase">&lt; BACK_TO_NODES</Link>
        </div>

        <!-- Live real-time view only - no persistent connect/disconnect history log is kept (see
             PR notes: building that would require a background poller diffing router state over
             time, which is out of scope for this pass). -->
        <div class="flex justify-between items-center">
            <span class="text-[10px] text-primary/50 uppercase">{{ sessions.length }} ACTIVE_SESSION(S)</span>
            <button :disabled="!selectedUsernames.length" @click="bulkKill"
                    class="inline-flex items-center gap-2 bg-red-600 disabled:opacity-30 disabled:cursor-not-allowed text-white px-4 py-1 text-xs font-bold uppercase hover:bg-red-500 transition-colors">
                <Zap :size="13" /> KILL_SELECTED ({{ selectedUsernames.length }})
            </button>
        </div>

        <div class="overflow-x-auto border border-primary/20 bg-surface/40">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                <tr>
                    <th class="p-3"><input type="checkbox" :checked="allChecked" @change="toggleAll"></th>
                    <th class="p-3">USERNAME</th>
                    <th class="p-3">ADDRESS</th>
                    <th class="p-3">UPTIME</th>
                    <th class="p-3">CALLER_ID</th>
                    <th class="p-3 text-right">CMDS</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-primary/10">
                <tr v-if="!sessions.length"><td colspan="6" class="p-4 text-center text-primary/40 uppercase">NO_ACTIVE_SESSIONS</td></tr>
                <tr v-for="session in sessions" :key="session['.id'] || session.name" class="hover:bg-primary/5">
                    <td class="p-3"><input type="checkbox" v-model="selected[session.name]"></td>
                    <td class="p-3 font-bold">
                        {{ session.name }}
                        <span v-if="session.is_duplicate" class="inline-flex items-center gap-1 text-yellow-400 text-[9px] ml-2 uppercase" title="Same user logged in twice">
                            <AlertTriangle :size="11" /> DUPLICATE
                        </span>
                    </td>
                    <td class="p-3 text-primary/70">{{ session.address }}</td>
                    <td class="p-3 text-primary/70">{{ session.uptime }}</td>
                    <td class="p-3 text-primary/50">{{ session['caller-id'] || '-' }}</td>
                    <td class="p-3 text-right">
                        <button @click="kill(session)" class="text-red-500 hover:text-red-300 font-bold uppercase text-[10px]">DISCONNECT</button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
