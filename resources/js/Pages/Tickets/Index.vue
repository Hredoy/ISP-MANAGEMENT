<script setup>
import { useForm } from '@inertiajs/vue3';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import { Ticket as TicketIcon } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

defineProps({ tickets: Object });

const form = useForm({ message: '', priority: 'normal' });

const submit = () => {
    form.post(route('dashboard.tickets.store'), { onSuccess: () => form.reset() });
};

const priorityClass = (priority) => (priority === 'urgent' ? 'text-red-500' : 'text-primary');
const statusClass = (status) => ({
    open: 'text-primary',
    assigned: 'text-blue-400',
    escalated: 'text-red-500',
    resolved: 'text-primary/40',
}[status] ?? 'text-primary');
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-black text-primary uppercase italic tracking-tighter flex items-center gap-2">
            <TicketIcon /> Support_Tickets
        </h1>

        <form @submit.prevent="submit" class="border border-primary/20 bg-surface p-4 space-y-3">
            <textarea v-model="form.message" rows="2" placeholder="Describe the issue..."
                      class="w-full bg-transparent border border-primary/40 text-primary text-[12px] p-2 outline-none"></textarea>
            <div class="flex items-center gap-3">
                <select v-model="form.priority" class="bg-surface border border-primary/40 text-primary text-[11px] p-2">
                    <option value="normal">Normal (24h SLA)</option>
                    <option value="urgent">Urgent (2h SLA)</option>
                </select>
                <button type="submit" :disabled="form.processing"
                        class="px-4 py-2 border border-primary/40 text-primary text-[11px] font-bold uppercase hover:bg-primary/10 disabled:opacity-50">
                    Create_Ticket
                </button>
            </div>
        </form>

        <div class="border border-primary/20 bg-surface">
            <table class="w-full text-[11px]">
                <thead>
                    <tr class="border-b border-primary/20 text-primary/50 uppercase">
                        <th class="text-left p-2">Subject</th>
                        <th class="text-left p-2">Category</th>
                        <th class="text-left p-2">Priority</th>
                        <th class="text-left p-2">Status</th>
                        <th class="text-left p-2">Assignee</th>
                        <th class="text-left p-2">SLA_Due</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets.data" :key="ticket.id" class="border-b border-primary/10">
                        <td class="p-2">{{ ticket.subject }}</td>
                        <td class="p-2">{{ ticket.category }}</td>
                        <td class="p-2 font-bold" :class="priorityClass(ticket.priority)">{{ ticket.priority }}</td>
                        <td class="p-2 font-bold" :class="statusClass(ticket.status)">{{ ticket.status }}</td>
                        <td class="p-2">{{ ticket.assignee?.name ?? 'Unassigned' }}</td>
                        <td class="p-2">{{ ticket.sla_due_at }}</td>
                    </tr>
                    <tr v-if="tickets.data.length === 0">
                        <td colspan="6" class="p-4 text-center text-primary/40">No tickets yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
