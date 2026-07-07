<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Power, PowerOff, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });
const props = defineProps({ router: Object, rules: Array });

const form = useForm({
    chain: 'forward',
    action: 'accept',
    protocol: '',
    dst_port: '',
    src_address: '',
    dst_address: '',
    comment: '',
});

const base = `/dashboard/mikrotik/${props.router.id}/firewall`;

const submit = () => form.post(base, { preserveScroll: true, onSuccess: () => form.reset('protocol', 'dst_port', 'src_address', 'dst_address', 'comment') });

const isDisabled = (rule) => rule.disabled === 'true' || rule.disabled === true;

const toggle = (rule) => router.put(`${base}/${encodeURIComponent(rule['.id'])}`, { disabled: !isDisabled(rule) }, { preserveScroll: true });
const destroyRule = (rule) => {
    if (confirm('DELETE_FIREWALL_RULE?')) {
        router.delete(`${base}/${encodeURIComponent(rule['.id'])}`, { preserveScroll: true });
    }
};
const move = (rule, direction) => router.post(`${base}/${encodeURIComponent(rule['.id'])}/move`, { direction }, { preserveScroll: true });
</script>

<template>
    <Head :title="`${router.name} - Firewall`" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> FIREWALL_RULES :: {{ router.name }}</h1>
            <Link href="/dashboard/mikrotik" class="text-[10px] text-primary/60 hover:text-primary uppercase">&lt; BACK_TO_NODES</Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 overflow-x-auto border border-primary/20 bg-surface/40">
                <table class="w-full text-left text-[11px]">
                    <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                    <tr>
                        <th class="p-3">CHAIN</th>
                        <th class="p-3">ACTION</th>
                        <th class="p-3">PROTO</th>
                        <th class="p-3">SRC/DST</th>
                        <th class="p-3">COMMENT</th>
                        <th class="p-3 text-right">CMDS</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                    <tr v-if="!rules.length"><td colspan="6" class="p-4 text-center text-primary/40 uppercase">NO_FIREWALL_RULES</td></tr>
                    <tr v-for="rule in rules" :key="rule['.id']" class="hover:bg-primary/5" :class="{ 'opacity-40': isDisabled(rule) }">
                        <td class="p-3 font-bold">{{ rule.chain }}</td>
                        <td class="p-3 text-primary/70 uppercase">{{ rule.action }}</td>
                        <td class="p-3 text-primary/70">{{ rule.protocol || '-' }}</td>
                        <td class="p-3 text-primary/70 text-[10px]">
                            <span v-if="rule['src-address']">SRC:{{ rule['src-address'] }}</span>
                            <span v-if="rule['dst-address']" class="block">DST:{{ rule['dst-address'] }}</span>
                            <span v-if="rule['dst-port']" class="block">PORT:{{ rule['dst-port'] }}</span>
                        </td>
                        <td class="p-3 text-primary/50 text-[10px]">{{ rule.comment || '-' }}</td>
                        <td class="p-3">
                            <div class="flex justify-end gap-3">
                                <button @click="move(rule, 'up')" class="text-primary/70 hover:text-primary" title="Move up"><ArrowUp :size="14" /></button>
                                <button @click="move(rule, 'down')" class="text-primary/70 hover:text-primary" title="Move down"><ArrowDown :size="14" /></button>
                                <button @click="toggle(rule)" class="text-primary/70 hover:text-primary" :title="isDisabled(rule) ? 'Enable' : 'Disable'">
                                    <Power v-if="isDisabled(rule)" :size="14" />
                                    <PowerOff v-else :size="14" />
                                </button>
                                <button @click="destroyRule(rule)" class="text-red-500 hover:text-red-300" title="Delete"><Trash2 :size="14" /></button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="border border-primary/30 bg-surface p-6">
                <h2 class="text-primary font-bold mb-4 uppercase text-xs tracking-widest">NEW_RULE</h2>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] mb-1">CHAIN</label>
                            <select v-model="form.chain" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                                <option value="input">input</option>
                                <option value="forward">forward</option>
                                <option value="output">output</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] mb-1">ACTION</label>
                            <select v-model="form.action" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                                <option value="accept">accept</option>
                                <option value="drop">drop</option>
                                <option value="reject">reject</option>
                                <option value="fasttrack-connection">fasttrack-connection</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">PROTOCOL</label>
                        <input v-model="form.protocol" type="text" placeholder="tcp / udp / icmp" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">DST_PORT</label>
                        <input v-model="form.dst_port" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">SRC_ADDRESS</label>
                        <input v-model="form.src_address" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">DST_ADDRESS</label>
                        <input v-model="form.dst_address" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">COMMENT</label>
                        <input v-model="form.comment" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <button :disabled="form.processing" class="w-full bg-primary text-black font-bold py-2 uppercase text-xs flex items-center justify-center gap-1 mt-2">
                        <Plus :size="13" /> ADD_RULE
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
