<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Users, Building2, ShieldCheck, UserCheck, FolderKanban } from 'lucide-vue-next';

const props = defineProps<{
    totalUsers: number;
    superadminCount: number;
    companyAdminCount: number;
    regularUserCount: number;
    projectCount: number;
    companyCount: number;
}>();

const cards = [
    { label: 'Total Users', value: props.totalUsers, icon: Users, color: '#2563EB' },
    { label: 'Superadmins', value: props.superadminCount, icon: ShieldCheck, color: '#DC2626' },
    { label: 'Company Admins', value: props.companyAdminCount, icon: UserCheck, color: '#D97706' },
    { label: 'Regular Users', value: props.regularUserCount, icon: Users, color: '#16A34A' },
    { label: 'Projects', value: props.projectCount, icon: FolderKanban, color: '#7C3AED' },
    { label: 'Companies', value: props.companyCount, icon: Building2, color: '#0891B2' },
];
</script>

<template>
    <Head title="Admin Dashboard" />
    <div>
        <h1 class="mb-6 text-xl font-bold text-[#0F1623]">Dashboard</h1>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-2xl border border-[#E4E7F0] bg-white p-5 transition-shadow hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[32px] font-bold text-[#0F1623]">{{ card.value }}</div>
                        <div class="mt-1 text-[13px] font-medium text-[#9BA3B8]">{{ card.label }}</div>
                    </div>
                    <component :is="card.icon" class="h-10 w-10 opacity-20" :style="{ color: card.color }" />
                </div>
            </div>
        </div>
    </div>
</template>
