<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { Copy, Check, UserPlus, UserCheck, X, Eye, Building2, Users, ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface CompanyProfile {
    id: number; name: string; logo_url: string | null; color: string | null;
    connect_code: string; admin?: string | null; user_count?: number; connection_id?: number | null;
}

interface PendingReceived {
    id: number;
    from_company: { id: number; name: string; logo_url: string | null; color: string | null; connect_code: string };
}

const props = defineProps<{
    myCompany: CompanyProfile;
    myConnections: CompanyProfile[];
    pendingReceived: PendingReceived[];
}>();

const searchCode = ref('');
const copied = ref(false);

function copyCode() {
    navigator.clipboard.writeText(props.myCompany.connect_code);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function doLookup() {
    if (!searchCode.value.trim()) return;
    router.get(`/connect/company/${searchCode.value.trim().toUpperCase()}`);
}

function sendConnect(companyId: number) {
    router.post('/connect/send', { company_id: companyId }, { preserveScroll: true, preserveState: true });
}

function acceptConnection(connectionId: number) {
    router.post(`/connect/${connectionId}/accept`, {}, { preserveScroll: true });
}

function declineConnection(connectionId: number) {
    router.post(`/connect/${connectionId}/decline`, {}, { preserveScroll: true });
}

function disconnect(connectionId: number) {
    if (!confirm('Disconnect from this company?')) return;
    router.delete(`/connect/${connectionId}`, { preserveScroll: true });
}

let pollInterval: ReturnType<typeof setInterval>;
onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ preserveScroll: true });
    }, 5000);
});
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head title="Connect" />
    <div class="mx-auto max-w-2xl py-4 px-4">
        <h1 class="mb-4 flex items-center gap-2 text-base font-bold text-[#0F1623]">
            <UserPlus class="h-5 w-5 text-[#2563EB]" /> Connect
        </h1>

        <!-- My Company Card -->
        <div class="mb-4 overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
            <div class="flex items-center gap-3 border-b border-[#E4E7F0] bg-[#F7F8FC] px-4 py-2.5">
                <Building2 class="h-4 w-4 text-[#5A6278]" />
                <span class="text-[13px] font-semibold text-[#0F1623]">My Company</span>
            </div>
            <div class="flex items-center gap-3 p-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl text-sm font-bold"
                    :style="{ backgroundColor: myCompany.color || '#3B82F6', color: '#fff' }"
                >
                    <img v-if="myCompany.logo_url" :src="myCompany.logo_url" :alt="myCompany.name" class="h-full w-full object-cover" />
                    <span v-else>{{ myCompany.name.charAt(0).toUpperCase() }}</span>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-medium text-[#0F1623]">{{ myCompany.name }}</div>
                    <div class="text-xs text-[#5A6278]" v-if="myCompany.admin">Admin: {{ myCompany.admin }}</div>
                    <div class="text-xs text-[#5A6278]">{{ myCompany.user_count }} users</div>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-[#E4E7F0] bg-[#F7F8FC] px-3 py-2">
                    <span class="text-xs font-mono font-bold text-[#2563EB] tracking-wider">{{ myCompany.connect_code }}</span>
                    <button class="text-[#9BA3B8] hover:text-[#2563EB]" @click="copyCode" :title="copied ? 'Copied!' : 'Copy code'">
                        <Copy v-if="!copied" class="h-4 w-4" />
                        <Check v-else class="h-4 w-4 text-green-500" />
                    </button>
                </div>
            </div>
            <div class="border-t border-[#E4E7F0] px-4 py-2 text-[10px] text-[#9BA3B8]">
                Share this code with other companies to connect.
            </div>
        </div>

        <!-- Lookup -->
        <div class="mb-4 rounded-2xl border border-[#E4E7F0] bg-white p-4">
            <h2 class="mb-3 text-sm font-bold text-[#0F1623]">Spectate Company</h2>
            <div class="flex gap-2">
                <input
                    v-model="searchCode"
                    type="text"
                    placeholder="Paste company code…"
                    class="flex-1 rounded-lg border border-[#E4E7F0] bg-white px-3 py-2 text-sm outline-none uppercase tracking-wider focus:border-[#2563EB]"
                    maxlength="8"
                    @keydown.enter="doLookup"
                />
                <Button size="sm" class="bg-[#2563EB] text-white hover:bg-[#1d4ed8]" :disabled="!searchCode.trim()" @click="doLookup">
                    <Eye class="mr-1 h-3.5 w-3.5" /> Spectate
                </Button>
            </div>
        </div>

        <!-- Pending Received -->
        <div v-if="pendingReceived.length > 0" class="mb-4 rounded-2xl border border-[#E4E7F0] bg-white p-4">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-[#0F1623]">
                <Eye class="h-4 w-4 text-amber-500" /> Pending Requests
            </h2>
            <div class="flex flex-col gap-2">
                <div v-for="req in pendingReceived" :key="req.id" class="flex items-center gap-3 rounded-xl border border-[#E4E7F0] bg-[#F7F8FC] px-3 py-2">
                    <Link :href="`/connect/company/${req.from_company.connect_code}`" class="flex items-center gap-3 flex-1 min-w-0">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg text-xs font-bold"
                            :style="{ backgroundColor: req.from_company.color || '#3B82F6', color: '#fff' }"
                        >
                            <img v-if="req.from_company.logo_url" :src="req.from_company.logo_url" class="h-full w-full object-cover" />
                            <span v-else>{{ req.from_company.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="text-sm font-medium text-[#0F1623] truncate">{{ req.from_company.name }}</div>
                    </Link>
                    <div class="flex items-center gap-1 shrink-0">
                        <Button size="sm" class="bg-green-600 text-white hover:bg-green-700" @click="acceptConnection(req.id)">
                            <Check class="mr-1 h-3 w-3" /> Accept
                        </Button>
                        <Button size="sm" variant="outline" class="border-red-200 text-red-600 hover:bg-red-50" @click="declineConnection(req.id)">
                            <X class="mr-1 h-3 w-3" /> Decline
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Connections -->
        <div class="rounded-2xl border border-[#E4E7F0] bg-white p-4">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold text-[#0F1623]">
                <UserCheck class="h-4 w-4 text-green-500" /> My Connections
            </h2>
            <div v-if="myConnections.length === 0" class="py-6 text-center text-xs text-[#9BA3B8]">
                No connections yet. Use the code above to connect with other companies.
            </div>
            <div v-else class="flex flex-col gap-2">
                <div v-for="conn in myConnections" :key="conn.id" class="flex items-center gap-3 rounded-xl border border-[#E4E7F0] bg-[#F7F8FC] px-3 py-2">
                    <Link :href="`/connect/company/${conn.connect_code}`" class="flex items-center gap-3 flex-1 min-w-0">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg text-xs font-bold"
                            :style="{ backgroundColor: conn.color || '#3B82F6', color: '#fff' }"
                        >
                            <img v-if="conn.logo_url" :src="conn.logo_url" :alt="conn.name" class="h-full w-full object-cover" />
                            <span v-else>{{ conn.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[#0F1623] truncate">{{ conn.name }}</div>
                            <div class="text-[10px] text-[#5A6278] truncate">{{ conn.admin ? 'Admin: ' + conn.admin : '' }} · {{ conn.user_count }} users</div>
                        </div>
                    </Link>
                    <div class="flex items-center gap-1 shrink-0">
                        <Link :href="`/connect/company/${conn.connect_code}`">
                            <Button size="sm" variant="outline" class="border-[#E4E7F0] text-[#5A6278] hover:bg-[#F7F8FC]">
                                <Eye class="mr-1 h-3 w-3" /> Spectate
                            </Button>
                        </Link>
                        <Button size="sm" variant="outline" class="border-red-200 text-red-600 hover:bg-red-50" @click="disconnect(conn.connection_id!)">
                            <X class="mr-1 h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
