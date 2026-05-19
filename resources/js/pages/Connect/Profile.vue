<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';

interface PartnerData {
    id: number; name: string; logo_url: string | null; color: string | null; connect_code: string;
}

interface FeedPost {
    id: number; content: string; image: string | null;
    created_at: string; user: { name: string; initials: string };
}

interface CompanyData {
    id: number; name: string; logo_url: string | null; color: string | null;
    connect_code: string; admin?: string | null; user_count?: number;
    connection_status: string | null; connection_id: number | null;
    projects_count?: number; partners?: PartnerData[];
}

const props = defineProps<{
    company: CompanyData;
    myCompanyCode: string;
    feedPosts: FeedPost[];
    isConnected: boolean;
}>();

const copied = ref(false);

function copyMyCode() {
    navigator.clipboard.writeText(props.myCompanyCode);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function sendConnect() {
    router.post('/connect/send', { company_id: props.company.id }, { preserveScroll: true });
}

function acceptConnection() {
    router.post(`/connect/${props.company.connection_id}/accept`, {}, { preserveScroll: true });
}

function declineConnection() {
    router.post(`/connect/${props.company.connection_id}/decline`, {}, { preserveScroll: true });
}

function disconnect() {
    if (!confirm('Disconnect from this company?')) return;
    router.delete(`/connect/${props.company.connection_id}`, { preserveScroll: true });
}

const color = props.company.color || '#185fa5';
</script>

<template>
    <Head :title="company.name" />
    <div class="mx-auto max-w-2xl py-4 px-4" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">
        <Link href="/connect" class="mb-3 inline-flex items-center gap-1 text-xs" style="color:#5A6278">
            <ArrowLeft class="h-3.5 w-3.5" /> Back to Connect
        </Link>

        <div class="overflow-hidden rounded-2xl border" style="background:#fff;border-color:#E4E7F0">
            <!-- Cover -->
            <div class="relative" :style="{ height: '110px', background: color }">
                <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 18px 18px"></div>
                <div class="absolute" style="bottom:-28px;left:20px">
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border-[3px] border-white" :style="{ background: '#fff' }">
                        <div class="flex h-[52px] w-[52px] items-center justify-center overflow-hidden rounded-xl" :style="{ background: color + '15' }">
                            <img v-if="company.logo_url" :src="company.logo_url" :alt="company.name" class="h-full w-full object-cover" />
                            <div v-else style="display:flex;gap:2px;flex-wrap:wrap;padding:6px;justify-content:center;align-items:center;width:100%">
                                <div v-for="i in 4" :key="i" class="rounded-sm" :style="{ width: '14px', height: '14px', background: color }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div style="padding: 16px 20px 20px">
                <!-- Top row -->
                <div class="flex items-start justify-between gap-3" style="margin-top:20px">
                    <div>
                        <div class="text-lg font-semibold" style="color:#0F1623;line-height:1.2">{{ company.name }}</div>
                        <div class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-medium tracking-wide" :style="{ background: color + '18', color: color }">{{ company.connect_code }}</div>
                    </div>

                    <!-- Action button -->
                    <template v-if="!company.connection_status">
                        <button class="flex items-center gap-1.5 rounded-xl px-4 py-2 text-[13px] font-medium text-white border-none cursor-pointer whitespace-nowrap transition-all active:scale-95" :style="{ background: color }" @click="sendConnect">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Connect +
                        </button>
                    </template>
                    <template v-else-if="company.connection_status === 'pending'">
                        <button class="flex items-center gap-1.5 rounded-xl px-4 py-2 text-[13px] font-medium border cursor-pointer whitespace-nowrap" :style="{ background: color + '15', color: color, borderColor: color + '40' }" disabled>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Connecting...
                        </button>
                    </template>
                    <template v-else-if="company.connection_status === 'connected' || company.connection_status === 'accepted'">
                        <button class="flex items-center gap-1.5 rounded-xl px-4 py-2 text-[13px] font-medium border cursor-pointer whitespace-nowrap" style="background:#EAF3DE;color:#27500A;border-color:#C0DD97" @click="disconnect">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Connected
                        </button>
                    </template>
                    <template v-else-if="company.connection_status === 'received_pending'">
                        <div class="flex gap-1.5">
                            <button class="flex items-center gap-1 rounded-xl px-3 py-2 text-[12px] font-medium text-white border-none cursor-pointer" style="background:#1D9E75" @click="acceptConnection">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Accept
                            </button>
                            <button class="flex items-center gap-1 rounded-xl px-3 py-2 text-[12px] font-medium border cursor-pointer" style="color:#DC2626;border-color:#FECACA;background:#FEF2F2" @click="declineConnection">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Decline
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Meta row -->
                <div class="mt-3 flex flex-wrap items-center gap-4 text-[12px]" style="color:#5A6278">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" style="color:#378ADD" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span class="font-medium" style="color:#0F1623">{{ company.user_count || 0 }}</span> People
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" style="color:#378ADD" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span class="font-medium" style="color:#0F1623">{{ company.projects_count || 0 }}</span> Projects
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" style="color:#378ADD" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span class="font-medium" style="color:#0F1623">{{ company.admin || '—' }}</span> Admin
                    </span>
                </div>

                <!-- Company Partners -->
                <div v-if="company.partners && company.partners.length > 0" class="mt-3">
                    <div class="mb-2 text-[11px] font-medium uppercase tracking-wide" style="color:#5A6278">Company Partners</div>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="partner in company.partners" :key="partner.id" class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-[12px] font-medium" :style="{ background: partner.color + '12', color: partner.color || '#0F1623' }">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-md text-[10px] font-bold" :style="{ background: partner.color || '#3B82F6', color: '#fff' }">
                                <img v-if="partner.logo_url" :src="partner.logo_url" :alt="partner.name" class="h-full w-full object-cover" />
                                <span v-else>{{ partner.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            {{ partner.name }}
                        </div>
                    </div>
                </div>
                <div v-else class="mt-3">
                    <div class="mb-2 text-[11px] font-medium uppercase tracking-wide" style="color:#5A6278">Company Partners</div>
                    <div class="text-[12px]" style="color:#9BA3B8">No partners yet.</div>
                </div>

                <div class="my-4 h-px" style="background:#E4E7F0"></div>

                <!-- Feed -->
                <div>
                    <div class="mb-2.5 text-[11px] font-medium uppercase tracking-wide" style="color:#5A6278">Feed</div>

                    <!-- Locked -->
                    <div v-if="!isConnected" class="flex flex-col items-center justify-center gap-2.5 rounded-xl border border-dashed p-6 text-center" style="background:#F7FBFF;border-color:#B5D4F4">
                        <svg class="h-7 w-7" fill="none" stroke="#85B7EB" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <p class="text-[13px]" style="color:#5A6278;line-height:1.5">
                            Connect with <strong :style="{ color }">{{ company.name }}</strong> to see their updates, project showcases, and announcements.
                        </p>
                    </div>

                    <!-- Unlocked feed -->
                    <div v-else class="flex flex-col gap-3">
                        <div v-if="feedPosts.length === 0" class="py-6 text-center text-[13px]" style="color:#9BA3B8">
                            No posts yet from {{ company.name }}.
                        </div>
                        <div v-for="post in feedPosts" :key="post.id" class="rounded-xl p-3.5" style="background:#F7F8FC;border:0.5px solid #E4E7F0">
                            <div class="flex items-center gap-2.5 mb-2">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold" :style="{ background: color + '20', color }">{{ post.user.initials }}</div>
                                <div>
                                    <div class="text-[13px] font-medium" style="color:#0F1623">{{ post.user.name }}</div>
                                    <div class="text-[11px]" style="color:#5A6278">{{ post.created_at }}</div>
                                </div>
                            </div>
                            <div class="text-[13px]" style="color:#5A6278;line-height:1.6">{{ post.content }}</div>
                            <div v-if="post.image" class="mt-2.5 flex h-24 items-center justify-center rounded-lg" style="background:#E6F1FB">
                                <img :src="post.image" class="h-full w-full rounded-lg object-cover" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
