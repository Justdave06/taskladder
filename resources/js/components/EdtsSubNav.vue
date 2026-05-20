<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const authUser = computed(() => page.props.auth.user as { name: string } | null);
const initials = computed(() => {
    const name = authUser.value?.name ?? '';
    return name
        .split(' ')
        .map(w => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
});
</script>

<template>
    <div class="flex items-center justify-between rounded-xl bg-[#185FA5] px-5 py-3 text-white">
        <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white">
                <svg class="h-5 w-5 text-[#185FA5]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
            <div>
                <div class="text-sm font-medium tracking-wide">e-DTS Portal</div>
                <div class="text-[11px] text-[#85B7EB]">Electronic Document Tracking System</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button class="flex items-center gap-1.5 rounded-lg bg-white/12 px-3 py-1.5 text-xs font-medium transition hover:bg-white/22">
                <Bell class="h-3.5 w-3.5" />
                3
            </button>
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#B5D4F4] text-xs font-semibold text-[#0C447C]">
                {{ initials }}
            </div>
        </div>
    </div>
</template>
