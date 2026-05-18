<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Heart, MessageSquareText, ImagePlus, Send } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';

interface PostUser {
    id: number; name: string; email: string;
}

interface PostLike {
    id: number; user_id: number;
}

interface PostData {
    id: number; project_id: number; user_id: number;
    content: string; image: string | null;
    created_at: string;
    user: PostUser;
    likes: PostLike[];
    likes_count: number;
    is_liked_by_me: boolean;
}

interface ProjectData {
    id: number; title: string; created_by: number;
}

const page = usePage();
const authUser = page.props.auth.user as { id: number; name: string };

const props = defineProps<{
    posts: PostData[];
    projects: ProjectData[];
    is_boss: boolean;
}>();

const showComposer = ref(false);
const newPost = ref('');
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

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

    if (selectedFile.value) {
formData.append('image', selectedFile.value);
}

    router.post('/bulletin/posts', formData, {
        preserveScroll: true,
        onSuccess: () => {
            newPost.value = '';
            selectedFile.value = null;
            showComposer.value = false;
        },
    });
}

function toggleLike(postId: number) {
    router.post(`/bulletin/posts/${postId}/like`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Bulletin" />

    <div class="mx-auto max-w-2xl py-4">
        <div class="mb-4 flex items-center">
            <h1 class="flex items-center gap-2 text-base font-bold text-blue-900 dark:text-blue-100">
                <MessageSquareText class="h-5 w-5" /> Bulletin
            </h1>
        </div>

        <!-- Admin Post Composer -->
        <div v-if="is_boss" class="mb-4 rounded-2xl border border-[#E4E7F0] bg-white">
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
                    <button class="flex items-center gap-1 text-xs text-[#9BA3B8] hover:text-[#5A6278]" @click="fileInputRef?.click()">
                        <ImagePlus class="h-4 w-4" /> Photo
                    </button>
                    <input ref="fileInputRef" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
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
                    <div>
                        <div class="text-sm font-medium text-[#0F1623]">{{ post.user.name }}</div>
                        <div class="text-[10px] text-[#9BA3B8]">{{ timeAgo(post.created_at) }}</div>
                    </div>
                </div>
                <div class="mb-2 whitespace-pre-wrap text-sm text-[#0F1623]/80">{{ post.content }}</div>
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
