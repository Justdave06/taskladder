<script setup lang="ts">
import AppBottomNav from '@/components/AppBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gradient-to-b from-blue-50 to-white dark:from-gray-950 dark:to-gray-900">
        <main class="w-full flex-1 px-4 lg:px-8 pb-28">
            <slot />
        </main>
        <AppBottomNav />
        <Toaster />
    </div>
</template>
