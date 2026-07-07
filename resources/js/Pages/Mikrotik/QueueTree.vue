<script setup>
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineOptions({ layout: ISPLayout });
const props = defineProps({ router: Object, nodes: Array });

const editing = ref(null);

const form = useForm({
    name: '',
    parent: 'global',
    packet_mark: '',
    max_limit: '',
    limit_at: '',
    priority: 8,
});

// Renders as a flat indented list (grouped by parent) rather than a literal drag-drop hierarchy
// builder - honest scope-down: reordering/reparenting is done via the form's PARENT field, not
// by dragging nodes on screen.
const tree = computed(() => {
    const byParent = {};
    for (const node of props.nodes) {
        const parent = node.parent || 'global';
        (byParent[parent] ??= []).push(node);
    }

    const depthOf = (name, seen = new Set()) => {
        if (name === 'global' || !name || seen.has(name)) {
            return 0;
        }
        seen.add(name);
        const parentNode = props.nodes.find((n) => n.name === name);
        return parentNode ? 1 + depthOf(parentNode.parent, seen) : 0;
    };

    return props.nodes.map((node) => ({ ...node, depth: depthOf(node.parent) })).sort((a, b) => a.depth - b.depth);
});

const startEdit = (node) => {
    editing.value = node.name;
    form.clearErrors();
    form.name = node.name;
    form.parent = node.parent;
    form.packet_mark = node['packet-mark'] || '';
    form.max_limit = node['max-limit'];
    form.limit_at = node['limit-at'] || '';
    form.priority = Number(node.priority) || 8;
};

const cancelEdit = () => {
    editing.value = null;
    form.reset();
    form.parent = 'global';
    form.priority = 8;
};

const submit = () => {
    if (editing.value) {
        form.put(`/dashboard/mikrotik/${props.router.id}/queue-tree/${encodeURIComponent(editing.value)}`, {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    } else {
        form.post(`/dashboard/mikrotik/${props.router.id}/queue-tree`, {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    }
};

const destroyNode = (name) => {
    if (confirm(`DELETE_QUEUE_NODE '${name}'?`)) {
        router.delete(`/dashboard/mikrotik/${props.router.id}/queue-tree/${encodeURIComponent(name)}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`${router.name} - Queue Tree`" />
    <div class="space-y-6">
        <div class="flex justify-between items-center border-b border-primary/30 pb-4">
            <h1 class="text-xl font-black text-primary tracking-tighter">>> QUEUE_TREE :: {{ router.name }}</h1>
            <Link href="/dashboard/mikrotik" class="text-[10px] text-primary/60 hover:text-primary uppercase">&lt; BACK_TO_NODES</Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 overflow-x-auto border border-primary/20 bg-surface/40">
                <table class="w-full text-left text-[11px]">
                    <thead class="bg-primary/10 text-primary uppercase border-b border-primary/20">
                    <tr>
                        <th class="p-3">NAME</th>
                        <th class="p-3">PARENT</th>
                        <th class="p-3">MAX_LIMIT</th>
                        <th class="p-3">PRIORITY</th>
                        <th class="p-3 text-right">CMDS</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-primary/10">
                    <tr v-if="!tree.length"><td colspan="5" class="p-4 text-center text-primary/40 uppercase">NO_QUEUE_NODES</td></tr>
                    <tr v-for="node in tree" :key="node.name" class="hover:bg-primary/5">
                        <td class="p-3 font-bold" :style="{ paddingLeft: `${12 + node.depth * 16}px` }">
                            <span v-if="node.depth > 0" class="text-primary/40 mr-1">&#8627;</span>{{ node.name }}
                        </td>
                        <td class="p-3 text-primary/70">{{ node.parent }}</td>
                        <td class="p-3 text-primary/70">{{ node['max-limit'] }}</td>
                        <td class="p-3 text-primary/70">{{ node.priority }}</td>
                        <td class="p-3">
                            <div class="flex justify-end gap-3">
                                <button @click="startEdit(node)" class="text-primary hover:text-ink" title="Edit"><Pencil :size="14" /></button>
                                <button @click="destroyNode(node.name)" class="text-red-500 hover:text-red-300" title="Delete"><Trash2 :size="14" /></button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="border border-primary/30 bg-surface p-6">
                <h2 class="text-primary font-bold mb-4 uppercase text-xs tracking-widest">{{ editing ? `EDIT :: ${editing}` : 'NEW_QUEUE_NODE' }}</h2>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="block text-[10px] mb-1">NAME</label>
                        <input v-model="form.name" type="text" :disabled="!!editing" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none disabled:opacity-50">
                        <p v-if="form.errors.name" class="text-red-400 text-[10px] mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">PARENT</label>
                        <input v-model="form.parent" type="text" placeholder="global" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">PACKET_MARK</label>
                        <input v-model="form.packet_mark" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">MAX_LIMIT (e.g. 10M/10M)</label>
                        <input v-model="form.max_limit" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                        <p v-if="form.errors.max_limit" class="text-red-400 text-[10px] mt-1">{{ form.errors.max_limit }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">LIMIT_AT (PCQ guaranteed rate)</label>
                        <input v-model="form.limit_at" type="text" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] mb-1">PRIORITY (1-8)</label>
                        <input v-model.number="form.priority" type="number" min="1" max="8" class="w-full bg-surface border border-primary/40 p-2 text-primary outline-none">
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button :disabled="form.processing" class="flex-1 bg-primary text-black font-bold py-2 uppercase text-xs flex items-center justify-center gap-1">
                            <Plus :size="13" /> {{ editing ? 'SAVE' : 'ADD_NODE' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit" class="px-4 border border-primary/40 text-primary/70 uppercase text-xs">CANCEL</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
