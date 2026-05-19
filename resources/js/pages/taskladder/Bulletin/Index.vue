<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Heart, MessageSquareText, ImagePlus, Send, MoreVertical, Trash2 } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';

interface PostUser {
    id: number; name: string; email: string; company?: { id: number; name: string; logo_url?: string | null; color?: string | null } | null;
}

interface PostLike {
    id: number; user_id: number;
}

interface PostData {
    id: number; user_id: number;
    content: string; image: string | null;
    created_at: string;
    user: PostUser;
    likes: PostLike[];
    likes_count: number;
    is_liked_by_me: boolean;
    visibility: string;
}

const page = usePage();
const authUser = page.props.auth.user as { id: number; name: string; company_id?: number | null };

const props = defineProps<{
    posts: PostData[];
    can_post: boolean;
}>();

const showComposer = ref(false);
const newPost = ref('');
const postVisibility = ref<'company' | 'public'>('company');
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const deleteDropdownPostId = ref<number | null>(null);
const deletingPostId = ref<number | null>(null);

const filteredPosts = computed(() => props.posts);

function timeAgo(date: string): string {
    const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000);

    if (seconds < 60) {
return 'just now';
}

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
return `${minutes}m ago`;
}

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
return `${hours}h ago`;
}

    const days = Math.floor(hours / 24);

    return `${days}d ago`;
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;

    if (!input.files?.[0]) {
return;
}

    selectedFile.value = input.files[0];
}

function submitPost() {
    if (!newPost.value.trim()) {
return;
}

    const formData = new FormData();
    formData.append('content', newPost.value);
    formData.append('visibility', postVisibility.value);

    if (selectedFile.value) {
formData.append('image', selectedFile.value);
}

    router.post('/bulletin/posts', formData, {
        preserveScroll: true,
        onSuccess: () => {
            newPost.value = '';
            selectedFile.value = null;
            postVisibility.value = 'company';
            showComposer.value = false;
        },
    });
}

function toggleLike(postId: number) {
    router.post(`/bulletin/posts/${postId}/like`, {}, { preserveScroll: true });
}

function confirmDeletePost(postId: number) {
    deleteDropdownPostId.value = null;
    deletingPostId.value = postId;
}

function cancelDeletePost() {
    deletingPostId.value = null;
}

function doDeletePost(postId: number) {
    router.delete(`/bulletin/posts/${postId}`, {
        preserveScroll: true,
        onSuccess: () => {
            deletingPostId.value = null;
        },
        onError: () => {
            deletingPostId.value = null;
        },
    });
}

function onDocumentClick() {
    deleteDropdownPostId.value = null;
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <Head title="Bulletin" />

    <div class="mx-auto max-w-2xl py-4">
        <div class="mb-4 flex items-center">
            <h1 class="flex items-center gap-2 text-base font-bold text-blue-900 dark:text-blue-100">
                <MessageSquareText class="h-5 w-5" /> Bulletin
            </h1>
        </div>

        <!-- Post Composer -->
        <div v-if="can_post" class="mb-4 rounded-2xl border border-[#E4E7F0] bg-white">
            <div
                class="flex cursor-pointer items-center gap-3 px-4 py-3 text-sm text-[#9BA3B8] hover:text-[#5A6278]"
                @click="showComposer = !showComposer"
            >
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#EEF3FF] text-sm font-semibold text-[#2563EB]">
                    {{ authUser.name.charAt(0).toUpperCase() }}
                </div>
                <span class="flex-1">Write a post…</span>
            </div>
            <div v-if="showComposer" class="border-t border-[#E4E7F0] px-4 pb-4 pt-3">
                <textarea
                    v-model="newPost"
                    rows="3"
                    placeholder="What's on your mind?"
                    class="w-full resize-none rounded-lg border border-[#E4E7F0] bg-white p-3 text-sm outline-none focus:border-[#2563EB]"
                />
                <div v-if="selectedFile" class="mt-2 flex items-center gap-2 rounded-lg bg-[#F0F2F8] px-3 py-2 text-xs text-[#5A6278]">
                    <span>{{ selectedFile.name }}</span>
                    <button class="ml-auto text-red-500 hover:text-red-700" @click="selectedFile = null">✕</button>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button class="flex items-center gap-1 text-xs text-[#9BA3B8] hover:text-[#5A6278]" @click="fileInputRef?.click()">
                            <ImagePlus class="h-4 w-4" /> Photo
                        </button>
                        <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
                        <div class="flex items-center gap-1 rounded-lg border border-[#E4E7F0] p-0.5">
                            <button
                                class="rounded-md px-2 py-1 text-[10px] font-medium transition"
                                :class="postVisibility === 'company' ? 'bg-[#2563EB] text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'"
                                @click="postVisibility = 'company'"
                            >Company</button>
                            <button
                                class="rounded-md px-2 py-1 text-[10px] font-medium transition"
                                :class="postVisibility === 'public' ? 'bg-[#2563EB] text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'"
                                @click="postVisibility = 'public'"
                            >Public</button>
                        </div>
                    </div>
                    <Button size="sm" class="bg-[#2563EB] text-white hover:bg-[#1d4ed8]" :disabled="!newPost.trim()" @click="submitPost">
                        <Send class="mr-1 h-3 w-3" /> Post
                    </Button>
                </div>
            </div>
        </div>

        <!-- Posts Feed -->
        <div v-if="filteredPosts.length === 0" class="rounded-2xl border border-dashed border-[#E4E7F0] px-6 py-12 text-center text-sm text-[#9BA3B8]">
            <MessageSquareText class="mx-auto mb-2 h-8 w-8" />
            No posts yet.
        </div>
        <div v-else class="flex flex-col gap-3">
            <div v-for="post in filteredPosts" :key="post.id" class="rounded-2xl border border-[#E4E7F0] bg-white p-4">
                <div class="mb-2 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#EEF3FF] text-sm font-semibold text-[#2563EB]">
                        {{ post.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <div class="text-sm font-medium text-[#0F1623]">{{ post.user.name }}</div>
                                <span v-if="post.user.company && post.user.company.id !== authUser.company_id" class="text-[10px] text-[#9BA3B8]">· {{ post.user.company.name }}</span>
                                <span
                                    v-if="post.visibility === 'public'"
                                    class="rounded-full bg-green-100 px-2 py-0.5 text-[9px] font-medium text-green-700"
                                >Public</span>
                                <span
                                    v-else
                                    class="rounded-full bg-blue-100 px-2 py-0.5 text-[9px] font-medium text-blue-700"
                                >Company</span>
                            </div>
                        <div class="text-[10px] text-[#9BA3B8]">{{ timeAgo(post.created_at) }}</div>
                    </div>
                    <div v-if="post.user_id === authUser.id" class="relative">
                        <button class="flex h-7 w-7 items-center justify-center rounded-lg text-[#9BA3B8] transition hover:bg-[#F0F2F8] hover:text-[#5A6278]" @click.stop="deleteDropdownPostId = deleteDropdownPostId === post.id ? null : post.id">
                            <MoreVertical class="h-4 w-4" />
                        </button>
                        <div v-if="deleteDropdownPostId === post.id" class="absolute right-0 top-8 z-50 min-w-[120px] rounded-lg border border-[#E4E7F0] bg-white py-1 shadow-lg">
                            <button class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-red-600 transition hover:bg-red-50" @click.stop="confirmDeletePost(post.id)">
                                <Trash2 class="h-3.5 w-3.5" />
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Delete confirmation -->
                <div v-if="deletingPostId === post.id" class="mb-2 flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2">
                    <span class="text-xs text-red-700">Delete this post?</span>
                    <button class="flex h-6 w-6 items-center justify-center rounded bg-red-500 text-white hover:bg-red-600" title="Confirm" @click="doDeletePost(post.id)">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <button class="flex h-6 w-6 items-center justify-center rounded bg-gray-300 text-white hover:bg-gray-400" title="Cancel" @click="cancelDeletePost">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div v-else class="mb-2 whitespace-pre-wrap text-sm text-[#0F1623]/80">{{ post.content }}</div>
                <img v-if="post.image" :src="'/storage/' + post.image" class="mb-2 w-full rounded-xl object-cover" />
                <div class="flex items-center gap-2 border-t border-[#E4E7F0] pt-2">
                    <button
                        class="flex items-center gap-1 text-xs transition-all"
                        :class="post.is_liked_by_me ? 'text-red-500' : 'text-[#9BA3B8] hover:text-red-400'"
                        @click="toggleLike(post.id)"
                    >
                        <Heart class="h-4 w-4" :class="post.is_liked_by_me ? 'fill-red-500' : ''" />
                        {{ post.likes_count || 'Like' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
