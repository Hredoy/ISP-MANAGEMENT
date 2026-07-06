<script setup>
import { ref, nextTick } from 'vue';
import ISPLayout from '@/Layouts/ISPLayout.vue';
import axios from 'axios';
import { Bot, Send, User } from 'lucide-vue-next';

defineOptions({ layout: ISPLayout });

const messages = ref([
    { from: 'bot', text: 'Hi! Ask me about bills, outages, or your connection status.', tier: null },
]);
const draft = ref('');
const sending = ref(false);
const scrollArea = ref(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollArea.value) {
            scrollArea.value.scrollTop = scrollArea.value.scrollHeight;
        }
    });
};

const send = async () => {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ from: 'user', text });
    draft.value = '';
    sending.value = true;
    scrollToBottom();

    try {
        const { data } = await axios.post(route('dashboard.chatbot.ask'), { message: text });
        messages.value.push({ from: 'bot', text: data.answer, tier: data.tier });
    } catch (e) {
        messages.value.push({ from: 'bot', text: 'Sorry, something went wrong. Please try again.', tier: 'error' });
    } finally {
        sending.value = false;
        scrollToBottom();
    }
};
</script>

<template>
    <div class="space-y-4 max-w-2xl">
        <h1 class="text-2xl font-black text-primary uppercase italic tracking-tighter flex items-center gap-2">
            <Bot /> AI_Assistant
        </h1>

        <div ref="scrollArea" class="border border-primary/20 bg-surface p-4 h-96 overflow-y-auto space-y-3">
            <div v-for="(msg, i) in messages" :key="i" class="flex gap-2" :class="msg.from === 'user' ? 'justify-end' : 'justify-start'">
                <div class="flex items-start gap-2 max-w-[80%]" :class="msg.from === 'user' ? 'flex-row-reverse' : ''">
                    <component :is="msg.from === 'user' ? User : Bot" :size="16" class="mt-1 text-primary/50 shrink-0" />
                    <div class="text-[12px] border border-primary/20 p-2" :class="msg.from === 'user' ? 'bg-primary/10' : 'bg-transparent'">
                        {{ msg.text }}
                        <span v-if="msg.tier" class="block text-[9px] text-primary/40 uppercase mt-1">{{ msg.tier }}</span>
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="send" class="flex gap-2">
            <input v-model="draft" type="text" placeholder="Type your question..."
                   class="flex-1 bg-surface border border-primary/40 text-primary text-[12px] p-2 outline-none" />
            <button type="submit" :disabled="sending"
                    class="px-4 border border-primary/40 text-primary hover:bg-primary/10 disabled:opacity-50 flex items-center gap-1">
                <Send :size="14" /> Send
            </button>
        </form>
    </div>
</template>
