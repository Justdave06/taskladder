<script setup lang="ts">
import { ArrowLeft, Search, Send } from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';

interface ChatMember {
    id: number; user_id: number; role_id: number | null; role: string | null;
    user: { id: number; name: string; email: string; last_active_at?: string | null };
}

interface ChatMessage {
    id: number; project_id: number; user_id: number;
    content: string; created_at: string;
    user: { id: number; name: string; email: string };
    _optimistic?: boolean;
}

const props = defineProps<{
    messages: ChatMessage[];
    contacts: ChatMember[];
    currentUserId: number;
    activeProjectTitle?: string;
}>();

const emit = defineEmits<{
    send: [text: string];
    selectContact: [userId: number];
}>();

const target = ref<number | null>(null);
const search = ref('');
const newMessage = ref('');
const msgsRef = ref<HTMLElement | null>(null);
const lastReadTimestamps = ref<Record<number, number>>({});

const filteredContacts = computed(() => {
    const q = search.value.toLowerCase();

    return props.contacts.filter(m => m.user.name.toLowerCase().includes(q));
});

const activeMessages = computed(() => {
    if (!target.value) {
return [];
}

    return props.messages
        .filter(m => m.user_id === target.value || m.user_id === props.currentUserId)
        .slice()
        .reverse();
});

const selectedContact = computed(() =>
    target.value ? props.contacts.find(m => m.user_id === target.value) : null,
);

function isActive(user: { last_active_at?: string | null } | undefined): boolean {
    if (!user?.last_active_at) {
return false;
}

    const diff = Date.now() - new Date(user.last_active_at).getTime();

    return diff < 120_000;
}

function lastMessagePreview(member: ChatMember): string {
    const last = props.messages.filter(m => m.user_id === member.user_id).pop();

    return last ? last.content.slice(0, 35) + (last.content.length > 35 ? '…' : '') : 'No messages yet';
}

function lastMessageTime(member: ChatMember): string {
    const last = props.messages.filter(m => m.user_id === member.user_id).pop();

    if (!last) {
return '';
}

    const diff = Date.now() - new Date(last.created_at).getTime();

    if (diff < 60_000) {
return 'now';
}

    const mins = Math.floor(diff / 60_000);

    if (mins < 60) {
return `${mins}m`;
}

    const hrs = Math.floor(mins / 60);

    return `${hrs}h`;
}

function unreadCount(member: ChatMember): number {
    const lastRead = lastReadTimestamps.value[member.user_id] || 0;

    return props.messages.filter(m => m.user_id === member.user_id && new Date(m.created_at).getTime() > lastRead).length;
}

function markRead(userId: number) {
    lastReadTimestamps.value[userId] = Date.now();
}

function select(userId: number) {
    target.value = userId;
    markRead(userId);
    emit('selectContact', userId);
}

function goBack() {
    target.value = null;
}

function showDaySep(msg: ChatMessage, idx: number): boolean {
    if (idx === 0) {
return true;
}

    const prev = activeMessages.value[idx - 1];

    if (!prev) {
return true;
}

    return new Date(msg.created_at).toDateString() !== new Date(prev.created_at).toDateString();
}

function msgDay(date: string): string {
    const d = new Date(date);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (d.toDateString() === today.toDateString()) {
return 'Today';
}

    if (d.toDateString() === yesterday.toDateString()) {
return 'Yesterday';
}

    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function bubbleTime(date: string): string {
    return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function autoResize(e: Event) {
    const el = e.target as HTMLTextAreaElement;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}

function sendQuick(text: string) {
    newMessage.value = text;
    handleSend();
}

function handleSend() {
    if (!newMessage.value.trim()) {
return;
}

    emit('send', newMessage.value);
    newMessage.value = '';
}

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
}

watch(() => activeMessages.value.length, () => {
    nextTick(() => {
        if (msgsRef.value) {
msgsRef.value.scrollTop = msgsRef.value.scrollHeight;
}
    });
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
        <!-- Contact list -->
        <template v-if="!target">
            <div class="flex items-center justify-between border-b border-[#E4E7F0] px-3.5 py-3">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Chats</span>
            </div>
            <div class="px-3 py-2">
                <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[#9BA3B8]"><Search class="h-3 w-3" /></span>
                    <input v-model="search" placeholder="Search messages…" class="w-full rounded-full border border-[#E4E7F0] bg-[#F4F6FB] py-1.5 pl-7 pr-3 text-[11px] outline-none focus:border-[#2563EB] focus:shadow-[0_0_0_2px_#EEF3FF]" />
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                <template v-if="contacts.length === 0">
                    <div class="py-8 text-center text-[10px] text-[#9BA3B8]">No team members yet.</div>
                </template>
                <template v-else>
                    <div v-if="filteredContacts.length === 0 && search" class="px-3.5 py-3 text-center text-[10px] text-[#9BA3B8]">No results for "{{ search }}"</div>
                    <div
                        v-for="m in filteredContacts"
                        :key="m.id"
                        class="flex cursor-pointer items-center gap-2.5 px-3.5 py-2.5 transition hover:bg-[#F4F6FB]"
                        @click="select(m.user_id)"
                    >
                        <div class="relative shrink-0">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-[12px] font-semibold text-white bg-[#2563EB]">
                                {{ m.user.name.charAt(0).toUpperCase() }}
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white" :class="isActive(m.user) ? 'bg-green-400' : 'bg-gray-300'" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <span class="truncate text-[12px] font-medium text-[#0F1623]">{{ m.user.name }}</span>
                                <span class="ml-1 shrink-0 text-[9px] text-[#9BA3B8]">{{ lastMessageTime(m) }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="flex-1 truncate text-[10px] text-[#9BA3B8]">{{ lastMessagePreview(m) }}</span>
                                <span v-if="unreadCount(m) > 0" class="flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[#2563EB] px-1 text-[9px] font-semibold text-white">{{ unreadCount(m) }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <!-- Conversation -->
        <template v-else>
            <div class="flex items-center gap-2 border-b border-[#E4E7F0] px-3 py-2.5">
                <button class="flex h-7 w-7 items-center justify-center rounded text-[#5A6278] hover:bg-[#F4F6FB]" @click="goBack">
                    <ArrowLeft class="h-4 w-4" />
                </button>
                <div class="relative shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#2563EB] text-[10px] font-semibold text-white">
                        {{ selectedContact?.user.name.charAt(0).toUpperCase() || '?' }}
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white" :class="isActive(selectedContact?.user) ? 'bg-green-400' : 'bg-gray-300'" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-[12px] font-medium text-[#0F1623]">{{ selectedContact?.user.name || 'Chat' }}</div>
                    <div class="text-[10px]" :class="isActive(selectedContact?.user) ? 'text-green-500' : 'text-[#9BA3B8]'">{{ isActive(selectedContact?.user) ? 'Active now' : 'Away' }}</div>
                </div>
            </div>
            <div ref="msgsRef" class="flex-1 overflow-y-auto px-3 py-2">
                <div v-if="activeMessages.length === 0" class="py-8 text-center text-[10px] text-[#9BA3B8]">No messages yet. Say hello!</div>
                <template v-for="(msg, i) in activeMessages" :key="msg.id">
                    <div v-if="showDaySep(msg, i)" class="flex items-center gap-2 py-1">
                        <span class="h-px flex-1 bg-[#E4E7F0]" />
                        <span class="text-[10px] text-[#9BA3B8]">{{ msgDay(msg.created_at) }}</span>
                        <span class="h-px flex-1 bg-[#E4E7F0]" />
                    </div>
                    <div class="flex" :class="msg.user_id === currentUserId ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[85%] rounded-[18px] px-3 py-2 text-[12px] leading-relaxed" :class="[msg.user_id === currentUserId ? 'rounded-br-[4px] bg-[#2563EB] text-white' : 'rounded-bl-[4px] bg-[#F0F2F8] text-[#0F1623]', msg._optimistic ? 'opacity-70' : '']">
                            {{ msg.content }}
                            <div class="mt-0.5 text-[9px]" :class="msg.user_id === currentUserId ? 'text-right text-white/60' : 'text-[#9BA3B8]'">{{ bubbleTime(msg.created_at) }}</div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex gap-1.5 overflow-x-auto border-t border-[#E4E7F0] px-3 py-1.5">
                <button class="shrink-0 rounded-full border border-[#E4E7F0] px-2.5 py-1 text-[10px] text-[#5A6278] transition hover:bg-[#F4F6FB]" @click="sendQuick('👍 Sounds good!')">👍 Sounds good!</button>
                <button class="shrink-0 rounded-full border border-[#E4E7F0] px-2.5 py-1 text-[10px] text-[#5A6278] transition hover:bg-[#F4F6FB]" @click="sendQuick('On it!')">On it!</button>
                <button class="shrink-0 rounded-full border border-[#E4E7F0] px-2.5 py-1 text-[10px] text-[#5A6278] transition hover:bg-[#F4F6FB]" @click="sendQuick('Checking now…')">Checking now…</button>
            </div>
            <div class="flex items-end gap-2 border-t border-[#E4E7F0] px-3 py-2.5">
                <div class="flex flex-1 items-center gap-2 rounded-[22px] border border-[#E4E7F0] bg-[#F4F6FB] px-3 py-1.5 focus-within:border-[#2563EB] focus-within:shadow-[0_0_0_2px_#EEF3FF]">
                    <textarea
                        v-model="newMessage"
                        rows="1"
                        class="max-h-20 flex-1 resize-none bg-transparent text-[12px] text-[#0F1623] outline-none placeholder:text-[#9BA3B8]"
                        placeholder="Type a message…"
                        @keydown="handleKey"
                        @input="autoResize"
                    />
                </div>
                <button class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#2563EB] text-white transition hover:bg-[#1d4ed8] active:scale-95 disabled:opacity-40" :disabled="!newMessage.trim()" @click="handleSend">
                    <Send class="h-4 w-4" />
                </button>
            </div>
        </template>
    </div>
</template>
