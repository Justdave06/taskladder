<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search, Send, Paperclip, Smile, Trash2, Pencil, X, Check, File, Image as ImageIcon, Loader, Phone, Video, PhoneOff, Mic, MicOff, VideoOff } from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useCall } from '../../../composables/useCall';
import { useReactions, REACTION_EMOJIS } from '../../../composables/useReactions';

interface UserData {
    id: number;
    name: string;
    email: string;
    last_active_at?: string | null;
}

interface AttachmentData {
    id: number;
    message_id: number | null;
    file_path: string;
    file_name: string;
    mime_type: string;
    file_size: number;
    url: string;
}

interface ReactionData {
    id: number;
    user_id: number;
    reaction: string;
    user: { id: number; name: string };
}

interface MessageData {
    id: number;
    user_id: number;
    recipient_id: number | null;
    content: string;
    created_at: string;
    updated_at?: string;
    user: { id: number; name: string; email: string };
    attachments?: AttachmentData[];
    reactions?: ReactionData[];
}

interface PageProps {
    contacts: UserData[];
    messages: MessageData[];
}

const page = usePage();
const pageProps = page.props as unknown as PageProps;
const authUser = page.props.auth.user as { id: number; name: string; email: string };

const contacts = ref(pageProps.contacts);
const allMessages = ref(pageProps.messages);
const optimisticMessages = ref<MessageData[]>([]);
const selectedContact = ref<UserData | null>(null);
const searchQuery = ref('');
const messageText = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const csrfToken = (page.props as any).csrf_token as string;

// Typing
const typingUser = ref<{ name: string } | null>(null);
const typingSafetyTimer = ref<ReturnType<typeof setTimeout> | null>(null);
let typingDebounce: ReturnType<typeof setTimeout> | null = null;
let typingStopTimeout: ReturnType<typeof setTimeout> | null = null;

// Uploads
const uploadingFiles = ref<{ name: string; progress: string }[]>([]);
const pendingAttachmentIds = ref<number[]>([]);

// Edit
const editingMsgId = ref<number | null>(null);
const editText = ref('');

// Delete
const deletingMsgId = ref<number | null>(null);

// Emoji
const showEmojiPicker = ref(false);

// Incoming call dialog
const incomingCall = ref<{ fromName: string; onAccept: () => void; onDecline: () => void } | null>(null);

// Reactions
const { showReactionPicker, toggleReactionPicker, closeReactionPicker, sendReaction, hasUserReacted, uniqueReactions } = useReactions();

// Call
const {
    state: callState,
    startCall,
    receiveOffer,
    receiveAnswer,
    receiveIceCandidate,
    endCall,
    remoteEnded,
    formatTimer,
    cleanup: cleanupCall,
} = useCall();

// Scrollbar auto-hide
let scrollHideTimer: ReturnType<typeof setTimeout>;
function onScroll() {
    const el = messagesContainer.value;
    if (!el) return;
    el.classList.add('scrolling');
    clearTimeout(scrollHideTimer);
    scrollHideTimer = setTimeout(() => {
        el.classList.remove('scrolling');
    }, 1500);
}

const emojiList = [
    '😊', '😄', '😅', '😂', '🤣', '😌', '😍', '🥰',
    '👍', '👎', '👏', '🙌', '💪', '🤝', '✌️', '🎉',
    '❤️', '💔', '🔥', '⭐', '✅', '❌', '💯', '🎯',
    '🙏', '💡', '📌', '🎈', '🚀', '💬', '👋', '🤔',
];

const avatarColors = [
    'bg-blue-100 text-blue-700', 'bg-green-100 text-green-700',
    'bg-amber-100 text-amber-700', 'bg-purple-100 text-purple-700',
    'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700',
];

const filteredContacts = computed(() => {
    if (!searchQuery.value.trim()) {
        return contacts.value;
    }

    const q = searchQuery.value.toLowerCase();

    return contacts.value.filter(c =>
        c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q),
    );
});

const conversation = computed(() => {
    if (!selectedContact.value) {
        return [];
    }

    const contactId = selectedContact.value.id;

    const server = allMessages.value.filter(m =>
        m.user_id === contactId || (m.user_id === authUser.id && m.recipient_id === contactId),
    );
    const serverIds = new Set(server.map(m => m.id));
    const local = optimisticMessages.value.filter(
        m => !serverIds.has(m.id) && (m.user_id === contactId || (m.user_id === authUser.id && m.recipient_id === contactId)),
    );

    return [...server, ...local].sort(
        (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    );
});

const lastMessagePerContact = computed(() => {
    const map = new Map<number, MessageData>();

    for (const m of allMessages.value) {
        const otherId = m.user_id === authUser.id ? m.recipient_id : m.user_id;

        if (!otherId || otherId === authUser.id) {
            continue;
        }

        const existing = map.get(otherId);

        if (!existing || new Date(m.created_at) > new Date(existing.created_at)) {
            map.set(otherId, m);
        }
    }

    for (const m of optimisticMessages.value) {
        const otherId = m.user_id === authUser.id ? m.recipient_id : m.user_id;

        if (!otherId || otherId === authUser.id) {
            continue;
        }

        const existing = map.get(otherId);

        if (!existing || new Date(m.created_at) > new Date(existing.created_at)) {
            map.set(otherId, m);
        }
    }

    return map;
});

const groupedConversation = computed(() => {
    const groups: { date: string; label: string; messages: MessageData[] }[] = [];
    const map = new Map<string, MessageData[]>();

    for (const m of conversation.value) {
        const day = m.created_at.slice(0, 10);

        if (!map.has(day)) {
            map.set(day, []);
        }

        map.get(day)!.push(m);
    }

    const today = new Date().toISOString().slice(0, 10);
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);

    for (const [day, msgs] of map) {
        let label: string;

        if (day === today) {
            label = 'Today';
        } else if (day === yesterday) {
            label = 'Yesterday';
        } else {
            label = new Date(day + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        groups.push({ date: day, label, messages: msgs });
    }

    groups.sort((a, b) => a.date.localeCompare(b.date));

    return groups;
});

function isActive(user: { last_active_at?: string | null }): boolean {
    if (!user.last_active_at) {
        return false;
    }

    return Date.now() - new Date(user.last_active_at).getTime() < 120_000;
}

function selectContact(contact: UserData) {
    selectedContact.value = contact;
    editingMsgId.value = null;
    deletingMsgId.value = null;
}

function autoResize() {
    const el = textareaRef.value;

    if (el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function getFileIcon(mime: string) {
    return mime.startsWith('image/') ? ImageIcon : File;
}

// ---- Call ----

function handleCall() {
    if (!selectedContact.value) return;

    (window as any).csrfToken = csrfToken;
    startCall(selectedContact.value.id, selectedContact.value.name, echo);
}

function handleEndCall() {
    if (!callState.value.peerId) return;

    (window as any).csrfToken = csrfToken;
    endCall(callState.value.peerId, callState.value.peerName, echo, csrfToken);
}

// ---- Emoji ----

function toggleEmojiPicker() {
    showEmojiPicker.value = !showEmojiPicker.value;
}

function insertEmoji(emoji: string) {
    messageText.value += emoji;
    showEmojiPicker.value = false;
    autoResize();
}

// ---- Typing ----

function sendTypingEvent(typing: boolean) {
    if (!selectedContact.value) return;

    fetch('/messages/typing', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ recipient_id: selectedContact.value.id, typing }),
    }).catch(() => {});
}

function debouncedTyping() {
    if (typingDebounce) clearTimeout(typingDebounce);
    if (typingStopTimeout) clearTimeout(typingStopTimeout);

    typingDebounce = setTimeout(() => {
        sendTypingEvent(true);

        typingStopTimeout = setTimeout(() => {
            sendTypingEvent(false);
        }, 2000);
    }, 300);
}

function handleInput() {
    autoResize();
    debouncedTyping();
}

// ---- File Upload ----

function triggerFileUpload() {
    fileInputRef.value?.click();
}

function handleFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = input.files;
    if (!files?.length) return;

    for (const file of Array.from(files)) {
        uploadFile(file);
    }

    input.value = '';
}

function uploadFile(file: File) {
    uploadingFiles.value.push({ name: file.name, progress: '0%' });

    const formData = new FormData();
    formData.append('file', file);

    fetch('/messages/attachments', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData,
    }).then(res => {
        if (!res.ok) throw new Error('Upload failed');
        return res.json();
    }).then((attachment: any) => {
        pendingAttachmentIds.value.push(attachment.id);
    }).catch(() => {
        // silent
    }).finally(() => {
        uploadingFiles.value = uploadingFiles.value.filter(f => f.name !== file.name);
    });
}

// ---- Send ----

function sendQuickReply(text: string) {
    messageText.value = text;

    if (text) {
        sendMessage();
    }
}

function sendMessage() {
    const text = messageText.value.trim();

    if (!text && pendingAttachmentIds.value.length === 0) {
        return;
    }

    if (!selectedContact.value) {
        return;
    }

    if (typingStopTimeout) {
        clearTimeout(typingStopTimeout);
        sendTypingEvent(false);
    }

    const tempId = -(Date.now() + Math.random());

    optimisticMessages.value.push({
        id: tempId,
        user_id: authUser.id,
        recipient_id: selectedContact.value.id,
        content: text,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        user: { id: authUser.id, name: authUser.name, email: '' },
        attachments: [],
    });

    fetch('/messages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            recipient_id: selectedContact.value.id,
            content: text,
            attachment_ids: pendingAttachmentIds.value,
        }),
    }).then(res => {
        if (!res.ok) throw new Error('Send failed');
        return res.json();
    }).then((realMsg: MessageData) => {
        optimisticMessages.value = optimisticMessages.value.filter(m => m.id !== tempId);

        if (!allMessages.value.some(m => m.id === realMsg.id)) {
            allMessages.value = [...allMessages.value, realMsg];
        }
    }).catch(() => {
        optimisticMessages.value = optimisticMessages.value.filter(m => m.id !== tempId);
    });

    messageText.value = '';
    pendingAttachmentIds.value = [];

    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
    }

    nextTick(() => scrollToBottom());
}

// ---- Edit ----

function startEdit(msg: MessageData) {
    editingMsgId.value = msg.id;
    editText.value = msg.content;
}

function cancelEdit() {
    editingMsgId.value = null;
    editText.value = '';
}

function saveEdit(msg: MessageData) {
    const text = editText.value.trim();
    if (!text) return;

    fetch('/messages/' + msg.id, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ content: text }),
    }).then(res => {
        if (!res.ok) throw new Error('Edit failed');
        return res.json();
    }).then((updated: MessageData) => {
        const idx = allMessages.value.findIndex(m => m.id === msg.id);
        if (idx !== -1) {
            allMessages.value[idx] = updated;
        }
    }).catch(() => {});

    editingMsgId.value = null;
    editText.value = '';
}

// ---- Delete ----

function confirmDelete(msg: MessageData) {
    deletingMsgId.value = msg.id;
}

function cancelDelete() {
    deletingMsgId.value = null;
}

function doDelete(msg: MessageData) {
    fetch('/messages/' + msg.id, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    }).then(res => {
        if (!res.ok) throw new Error('Delete failed');
        allMessages.value = allMessages.value.filter(m => m.id !== msg.id);
        optimisticMessages.value = optimisticMessages.value.filter(m => m.id !== msg.id);
    }).catch(() => {});

    deletingMsgId.value = null;
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

watch(
    () => [(page.props as any).messages, (page.props as any).contacts],
    ([newMessages, newContacts]: any[]) => {
        allMessages.value = (newMessages ?? []) as MessageData[];
        contacts.value = (newContacts ?? []) as UserData[];
    },
    { immediate: true },
);

watch(conversation, () => {
    nextTick(() => scrollToBottom());
});

let pollInterval: ReturnType<typeof setInterval>;
let echo: any = null;

onMounted(async () => {
    pollInterval = setInterval(() => {
        router.reload({ only: ['messages', 'contacts'] });
    }, 2000);

    try {
        const { default: Echo } = await import('laravel-echo');
        const Pusher = (await import('pusher-js')).default;

        (window as any).Pusher = Pusher;

        echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        const channel = echo.private(`App.Models.User.${authUser.id}`);

        channel.listen('.MessageSent', (data: any) => {
            const idx = allMessages.value.findIndex(m => m.id === data.id);

            if (idx !== -1) {
                allMessages.value[idx] = data;
            } else {
                allMessages.value = [...allMessages.value, data];
            }
        });

        channel.listen('.MessageReacted', (data: any) => {
            const idx = allMessages.value.findIndex(m => m.id === data.id);

            if (idx !== -1) {
                allMessages.value[idx] = data;
            }
        });

        channel.listen('.UserTyping', (data: any) => {
            if (data.sender_id === selectedContact.value?.id) {
                if (data.typing) {
                    typingUser.value = { name: data.sender_name };

                    if (typingSafetyTimer.value) {
                        clearTimeout(typingSafetyTimer.value);
                    }

                    typingSafetyTimer.value = setTimeout(() => {
                        typingUser.value = null;
                        typingSafetyTimer.value = null;
                    }, 4000);
                } else {
                    typingUser.value = null;

                    if (typingSafetyTimer.value) {
                        clearTimeout(typingSafetyTimer.value);
                        typingSafetyTimer.value = null;
                    }
                }
            }
        });

        channel.listen('.CallEvent', async (data: any) => {
            (window as any).csrfToken = csrfToken;

            if (data.type === 'offer') {
                closeReactionPicker();
                await receiveOffer(
                    data.sender_id,
                    data.sender_name,
                    data.data,
                    echo,
                    (name, onAccept, onDecline) => {
                        incomingCall.value = { fromName: name, onAccept, onDecline };
                    },
                );
            } else if (data.type === 'answer') {
                await receiveAnswer(data.data);
            } else if (data.type === 'ice-candidate') {
                await receiveIceCandidate(data.data);
            } else if (data.type === 'end') {
                remoteEnded(csrfToken);
            }
        });
    } catch {
        // Echo connection failed — polling still works
    }

    if (pageProps.contacts.length > 0) {
        selectContact(pageProps.contacts[0]);
    }
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }

    if (echo) {
        try {
            echo.leave(`App.Models.User.${authUser.id}`);
            echo.disconnect();
        } catch {
            // ignore cleanup errors
        }
    }

    cleanupCall();
});
</script>

<template>
    <Head title="Messages" />
    <div class="flex h-[calc(100vh-5rem)] overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white shadow-sm">
        <!-- Left panel: Contacts -->
        <aside class="flex w-[280px] shrink-0 flex-col border-r border-[#E4E7F0] bg-white">
            <div class="border-b border-[#E4E7F0] px-4 py-[18px]">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">Messages</span>
            </div>
            <div class="border-b border-[#E4E7F0] px-3 py-2.5">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#9BA3B8]" />
                    <input v-model="searchQuery" type="text" placeholder="Search people…" class="w-full rounded-lg border border-[#E4E7F0] bg-[#F7F8FC] py-2 pl-8 pr-3 text-[12px] outline-none focus:border-[#2563EB]" />
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                <div v-if="filteredContacts.length === 0" class="px-4 py-12 text-center text-[11px] text-[#9BA3B8]">
                    No contacts found.
                </div>
                <button
                    v-for="contact in filteredContacts"
                    :key="contact.id"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-[#F0F2F8]"
                    :class="selectedContact?.id === contact.id ? 'bg-[#EEF3FF]' : ''"
                    @click="selectContact(contact)"
                >
                    <div class="relative shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-[13px] font-semibold" :class="avatarColors[contact.id % avatarColors.length]">
                            {{ contact.name.charAt(0).toUpperCase() }}
                        </div>
                        <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-white" :class="isActive(contact) ? 'bg-green-400' : 'bg-gray-300'" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <span class="truncate text-[13px] font-medium text-[#0F1623]">{{ contact.name }}</span>
                            <span v-if="isActive(contact)" class="ml-1 shrink-0 text-[9px] font-semibold text-green-500">ONLINE</span>
                        </div>
                        <div class="truncate text-[11px] text-[#9BA3B8]">
                            {{ lastMessagePerContact.get(contact.id)?.content || 'No messages yet' }}
                        </div>
                    </div>
                </button>
            </div>
        </aside>

        <!-- Right panel: Conversation -->
        <main class="relative flex flex-1 flex-col bg-white">
            <!-- Header -->
            <div class="flex items-center gap-3 border-b border-[#E4E7F0] px-5 py-3">
                <template v-if="selectedContact">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[12px] font-semibold" :class="avatarColors[selectedContact.id % avatarColors.length]">
                        {{ selectedContact.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[14px] font-semibold text-[#0F1623]">{{ selectedContact.name }}</span>
                            <span class="flex items-center gap-1 text-[10px] font-semibold" :class="isActive(selectedContact) ? 'text-green-500' : 'text-[#9BA3B8]'">
                                <span class="inline-block h-2 w-2 rounded-full" :class="isActive(selectedContact) ? 'bg-green-400' : 'bg-gray-300'" />
                                {{ isActive(selectedContact) ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                        <div v-if="typingUser" class="text-[11px] text-[#2563EB] italic">
                            typing…
                        </div>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <button class="flex h-8 w-8 items-center justify-center text-[#2563EB] transition hover:opacity-70" title="Voice call" @click="handleCall">
                            <Phone class="h-5 w-5" />
                        </button>
                        <button class="flex h-8 w-8 items-center justify-center text-[#2563EB] transition hover:opacity-70" title="Video call" @click="handleCall">
                            <Video class="h-5 w-5" />
                        </button>
                    </div>
                </template>
                <div v-else class="text-[14px] font-semibold text-[#9BA3B8]">
                    Select a conversation
                </div>
            </div>

            <!-- Messages area -->
            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto bg-[#F7F8FC] px-5 py-4 messages-scroll"
                @scroll="onScroll"
            >
                <template v-if="groupedConversation.length > 0">
                    <template v-for="group in groupedConversation" :key="group.date">
                        <div class="mb-4 text-center">
                            <span class="inline-block rounded-full bg-[#E4E7F0] px-3 py-1 text-[10px] font-medium text-[#5A6278]">{{ group.label }}</span>
                        </div>
                        <template v-for="msg in group.messages" :key="msg.id">
                            <!-- Missed call system message -->
                            <div v-if="msg.type === 'missed_call'" class="mb-2.5 flex justify-center">
                                <div class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-[11px] text-[#9BA3B8]">
                                    <PhoneOff class="h-3.5 w-3.5" />
                                    Missed call
                                </div>
                            </div>

                            <!-- Regular message -->
                            <div v-else class="group relative mb-2.5 flex" :class="msg.user_id === authUser.id ? 'justify-end' : 'justify-start'">
                                <div
                                    class="max-w-[70%] rounded-2xl px-4 py-2.5 text-[13px] leading-snug"
                                    :class="msg.user_id === authUser.id
                                        ? 'rounded-br-md bg-[#2563EB] text-white'
                                        : 'rounded-bl-md border border-[#E4E7F0] bg-white text-[#0F1623]'"
                                >
                                    <div v-if="msg.user_id !== authUser.id" class="mb-1 text-[10px] font-medium text-[#9BA3B8]">{{ msg.user.name }}</div>

                                    <!-- Attachments -->
                                    <div v-if="msg.attachments && msg.attachments.length > 0" class="mb-2 space-y-1.5">
                                        <a
                                            v-for="att in msg.attachments"
                                            :key="att.id"
                                            :href="`/messages/attachments/${att.id}/download`"
                                            class="flex items-center gap-2 rounded-lg p-2 text-[12px] transition"
                                            :class="msg.user_id === authUser.id ? 'bg-blue-600 hover:bg-blue-700' : 'bg-[#F0F2F8] hover:bg-[#E4E7F0]'"
                                        >
                                            <component :is="getFileIcon(att.mime_type)" class="h-4 w-4 shrink-0" />
                                            <span class="truncate">{{ att.file_name }}</span>
                                            <span class="shrink-0 opacity-60">{{ formatFileSize(att.file_size) }}</span>
                                        </a>
                                    </div>

                                    <!-- Reactions display -->
                                    <div v-if="msg.reactions && msg.reactions.length > 0" class="mb-1 flex flex-wrap gap-0.5">
                                        <span
                                            v-for="r in uniqueReactions(msg.reactions)"
                                            :key="r.reaction"
                                            class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px]"
                                            :class="msg.user_id === authUser.id ? 'bg-blue-500/40' : 'bg-[#E4E7F0]'"
                                            :title="r.users.join(', ')"
                                        >
                                            {{ r.reaction }}
                                            <span v-if="r.count > 1" class="font-medium opacity-70">{{ r.count }}</span>
                                        </span>
                                    </div>

                                    <!-- Editing state -->
                                    <template v-if="editingMsgId === msg.id">
                                        <div class="rounded-xl bg-white p-3 shadow-md">
                                            <textarea
                                                v-model="editText"
                                                class="w-full resize-none rounded-lg border border-[#E4E7F0] bg-white p-2.5 text-[13px] text-[#0F1623] outline-none focus:border-[#2563EB] focus:ring-1 focus:ring-[#2563EB]/20"
                                                rows="2"
                                                @keydown.enter.prevent="saveEdit(msg)"
                                                @keydown.escape="cancelEdit"
                                            />
                                            <div class="mt-2 flex items-center justify-between">
                                                <span class="text-[10px] text-[#9BA3B8]">Press Esc to cancel</span>
                                                <div class="flex gap-2">
                                                    <button class="inline-flex items-center gap-1.5 rounded-lg border border-[#E4E7F0] bg-white px-3 py-1.5 text-[11px] font-medium text-[#5A6278] transition hover:bg-[#F0F2F8]" title="Cancel" @click="cancelEdit">
                                                        <X class="h-3.5 w-3.5" />
                                                        Cancel
                                                    </button>
                                                    <button class="inline-flex items-center gap-1.5 rounded-lg bg-[#2563EB] px-3 py-1.5 text-[11px] font-medium text-white transition hover:bg-[#1d4ed8]" title="Save" @click="saveEdit(msg)">
                                                        <Check class="h-3.5 w-3.5" />
                                                        Save
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Normal content -->
                                    <template v-else>
                                        <!-- Action buttons on hover -->
                                        <div v-if="msg.user_id === authUser.id" class="absolute right-0 top-0 hidden -translate-y-full gap-1 p-1 group-hover:flex">
                                            <button v-if="deletingMsgId !== msg.id" class="flex h-6 w-6 items-center justify-center rounded bg-white text-[#9BA3B8] shadow hover:bg-red-50 hover:text-red-500" title="Delete" @click="confirmDelete(msg)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                            <button class="flex h-6 w-6 items-center justify-center rounded bg-white text-[#9BA3B8] shadow hover:bg-blue-50 hover:text-blue-500" title="Edit" @click="startEdit(msg)">
                                                <Pencil class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <!-- Delete confirmation -->
                                        <div v-if="deletingMsgId === msg.id" class="flex items-center gap-2">
                                            <span class="text-[11px]">Delete?</span>
                                            <button class="flex h-6 w-6 items-center justify-center rounded bg-red-500 text-white hover:bg-red-600" title="Confirm delete" @click="doDelete(msg)">
                                                <Check class="h-3.5 w-3.5" />
                                            </button>
                                            <button class="flex h-6 w-6 items-center justify-center rounded bg-gray-300 text-white hover:bg-gray-400" title="Cancel" @click="cancelDelete">
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </div>

                                        <div v-else class="whitespace-pre-wrap break-words">
                                            {{ msg.content }}
                                            <span v-if="msg.updated_at && msg.updated_at !== msg.created_at" class="ml-1 text-[9px] opacity-50">(edited)</span>
                                        </div>
                                    </template>

                                    <div class="mt-1 flex items-end justify-between gap-2">
                                        <span class="text-[9px]" :class="msg.user_id === authUser.id ? 'text-blue-200' : 'text-[#9BA3B8]'">
                                            {{ new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                        </span>
                                        <div class="hidden gap-0.5 group-hover:flex">
                                            <div class="relative">
                                                <button class="flex h-5 w-5 items-center justify-center rounded-full text-[9px] hover:bg-black/10" title="React" @click.stop="toggleReactionPicker(msg.id)">
                                                    <Smile class="h-3 w-3" />
                                                </button>
                                                <div v-if="showReactionPicker === msg.id" class="absolute bottom-6 z-50 flex gap-0.5 rounded-xl border border-[#E4E7F0] bg-white p-1.5 shadow-lg" :class="msg.user_id === authUser.id ? 'right-0' : 'left-0'">
                                                    <button
                                                        v-for="emoji in REACTION_EMOJIS"
                                                        :key="emoji"
                                                        class="flex h-7 w-7 items-center justify-center rounded-md text-[15px] transition hover:scale-125 hover:bg-[#F0F2F8]"
                                                        :class="{ 'scale-110': hasUserReacted(msg.reactions, authUser.id, emoji) }"
                                                        @click="sendReaction(msg.id, emoji, csrfToken)"
                                                    >
                                                        {{ emoji }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>
                </template>
                <div v-else-if="selectedContact" class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">No messages yet</span>
                    <p class="mt-1 text-[12px] text-[#9BA3B8]">Start a conversation with {{ selectedContact.name }}.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <button class="rounded-full border border-[#E4E7F0] bg-white px-4 py-2 text-[11px] font-medium text-[#5A6278] transition hover:border-[#2563EB] hover:text-[#2563EB]" @click="sendQuickReply('Hello! How are you?')">
                            Hello!
                        </button>
                        <button class="rounded-full border border-[#E4E7F0] bg-white px-4 py-2 text-[11px] font-medium text-[#5A6278] transition hover:border-[#2563EB] hover:text-[#2563EB]" @click="sendQuickReply('Got it, thanks!')">
                            Got it
                        </button>
                        <button class="rounded-full border border-[#E4E7F0] bg-white px-4 py-2 text-[11px] font-medium text-[#5A6278] transition hover:border-[#2563EB] hover:text-[#2563EB]" @click="sendQuickReply('I\'ll check on that.')">
                            I'll check
                        </button>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">No conversation selected</span>
                    <p class="mt-1 text-[12px] text-[#9BA3B8]">Choose a contact from the left panel to start chatting.</p>
                </div>
            </div>

            <!-- Input area -->
            <div v-if="selectedContact" class="border-t border-[#E4E7F0] bg-white px-4 py-3">
                <!-- Upload progress -->
                <div v-if="uploadingFiles.length > 0" class="mb-2 space-y-1">
                    <div v-for="uf in uploadingFiles" :key="uf.name" class="flex items-center gap-2 text-[11px] text-[#5A6278]">
                        <Loader class="h-3 w-3 animate-spin" />
                        <span>{{ uf.name }}</span>
                    </div>
                </div>

                <!-- Pending attachments preview -->
                <div v-if="pendingAttachmentIds.length > 0" class="mb-2 flex flex-wrap gap-1.5">
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-medium text-blue-700">
                        {{ pendingAttachmentIds.length }} file(s) attached
                    </span>
                </div>

                <div class="flex items-end gap-2">
                    <div class="relative flex-1">
                        <textarea
                            ref="textareaRef"
                            v-model="messageText"
                            rows="1"
                            placeholder="Type a message…"
                            class="min-h-[40px] max-h-[120px] w-full resize-none rounded-xl border border-[#E4E7F0] bg-[#F7F8FC] px-4 py-2.5 pr-20 text-[13px] outline-none focus:border-[#2563EB]"
                            @input="handleInput"
                            @keydown.enter.prevent="sendMessage"
                        />
                        <div class="absolute bottom-1.5 right-2 flex items-center gap-0.5">
                            <button class="flex h-7 w-7 items-center justify-center rounded-lg text-[#9BA3B8] transition hover:bg-[#E4E7F0] hover:text-[#2563EB]" title="Emoji" @click="toggleEmojiPicker">
                                <Smile class="h-4 w-4" />
                            </button>
                            <button class="flex h-7 w-7 items-center justify-center rounded-lg text-[#9BA3B8] transition hover:bg-[#E4E7F0] hover:text-[#2563EB]" title="Attach file" @click="triggerFileUpload">
                                <Paperclip class="h-4 w-4" />
                            </button>

                            <!-- Emoji picker popover -->
                            <div v-if="showEmojiPicker" class="absolute bottom-10 right-0 z-50 grid w-[200px] grid-cols-8 gap-1 rounded-xl border border-[#E4E7F0] bg-white p-2 shadow-lg">
                                <button
                                    v-for="emoji in emojiList"
                                    :key="emoji"
                                    class="flex h-8 w-8 items-center justify-center rounded-md text-[16px] hover:bg-[#F0F2F8]"
                                    @click="insertEmoji(emoji)"
                                >
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <button
                        class="flex h-[40px] w-[40px] shrink-0 items-center justify-center rounded-xl bg-[#2563EB] text-white transition hover:bg-[#1d4ed8] active:scale-95 disabled:opacity-40"
                        :disabled="!messageText.trim() && pendingAttachmentIds.length === 0"
                        @click="sendMessage"
                    >
                        <Send class="h-4 w-4" />
                    </button>
                </div>
                <input ref="fileInputRef" type="file" class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.csv" @change="handleFileSelected" />
            </div>
        </main>

        <!-- Call overlay -->
        <div v-if="callState.status !== 'idle'" class="absolute inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-80 rounded-2xl bg-white p-6 text-center shadow-2xl">
                <template v-if="callState.status === 'connecting' || callState.status === 'ringing'">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-[#EEF3FF]">
                        <Phone class="h-8 w-8 animate-pulse text-[#2563EB]" />
                    </div>
                    <p class="mb-1 text-[15px] font-semibold text-[#0F1623]">{{ callState.peerName }}</p>
                    <p class="mb-6 text-[12px] text-[#9BA3B8]">{{ callState.isCaller ? 'Calling…' : 'Incoming call…' }}</p>
                    <div class="flex justify-center gap-4">
                        <button class="flex h-12 w-12 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600" title="End call" @click="handleEndCall">
                            <PhoneOff class="h-5 w-5" />
                        </button>
                    </div>
                </template>

                <template v-if="callState.status === 'connected'">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                        <Video class="h-8 w-8 text-green-500" />
                    </div>
                    <p class="mb-1 text-[15px] font-semibold text-[#0F1623]">{{ callState.peerName }}</p>
                    <p class="mb-6 text-[12px] text-[#2563EB] font-mono">{{ formatTimer(callState.timer) }}</p>
                    <div class="flex justify-center gap-4">
                        <button class="flex h-12 w-12 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600" title="End call" @click="handleEndCall">
                            <PhoneOff class="h-5 w-5" />
                        </button>
                    </div>
                </template>

                <template v-if="callState.status === 'ended'">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
                        <PhoneOff class="h-8 w-8 text-[#9BA3B8]" />
                    </div>
                    <p class="text-[15px] font-semibold text-[#0F1623]">Call ended</p>
                </template>
            </div>
        </div>

        <!-- Incoming call dialog -->
        <div v-if="incomingCall" class="absolute inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="w-80 rounded-2xl bg-white p-6 text-center shadow-2xl">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-[#EEF3FF]">
                    <Phone class="h-8 w-8 animate-pulse text-[#2563EB]" />
                </div>
                <p class="mb-1 text-[15px] font-semibold text-[#0F1623]">{{ incomingCall.fromName }}</p>
                <p class="mb-6 text-[12px] text-[#9BA3B8]">Incoming call…</p>
                <div class="flex justify-center gap-6">
                    <button class="flex h-12 w-12 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600" title="Decline" @click="incomingCall.onDecline(); incomingCall = null">
                        <PhoneOff class="h-5 w-5" />
                    </button>
                    <button class="flex h-12 w-12 items-center justify-center rounded-full bg-green-500 text-white transition hover:bg-green-600" title="Accept" @click="incomingCall.onAccept(); incomingCall = null">
                        <Phone class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.messages-scroll {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
    transition: scrollbar-color 0.3s ease;
}

.messages-scroll::-webkit-scrollbar {
    width: 4px;
    background: transparent;
}

.messages-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.messages-scroll::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
    transition: background 0.3s ease;
}

.messages-scroll:hover::-webkit-scrollbar-thumb,
.messages-scroll.scrolling::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.12);
}

.messages-scroll:hover,
.messages-scroll.scrolling {
    scrollbar-color: rgba(0, 0, 0, 0.12) transparent;
}
</style>
