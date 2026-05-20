<script setup lang="ts">
import { ref, computed, shallowRef, type Component, defineAsyncComponent } from 'vue';
import EdtsSubNav from '@/components/EdtsSubNav.vue';
import EdtsSidebar from '@/components/EdtsSidebar.vue';

const props = defineProps<{
    documents: any[];
    departments: any[];
    docTypes: any[];
    isAdmin: boolean;
    adminDepartments: any[];
    adminDocs: any[];
    metrics: { total: number; pending: number; in_transit: number; completed: number; rejected: number; requestees: number; high: number; medium: number; low: number };
    allUsers: any[];
}>();

const activeSection = ref('home');
const sidebarCollapsed = ref(false);

interface SectionMap {
    [key: string]: Component;
}

const sections: SectionMap = {
    home: defineAsyncComponent(() => import('./sections/HomeSection.vue')),
    request: defineAsyncComponent(() => import('./sections/RequestSection.vue')),
    track: defineAsyncComponent(() => import('./sections/TrackSection.vue')),
    'my-requests': defineAsyncComponent(() => import('./sections/MyRequestsSection.vue')),
    'admin-dashboard': defineAsyncComponent(() => import('./sections/AdminDashboard.vue')),
    'admin-queue': defineAsyncComponent(() => import('./sections/AdminQueue.vue')),
    'admin-records': defineAsyncComponent(() => import('./sections/AdminRecords.vue')),
    'manage-depts': defineAsyncComponent(() => import('./sections/ManageDepartments.vue')),
    'manage-doctypes': defineAsyncComponent(() => import('./sections/ManageDocTypes.vue')),
    'manage-admins': defineAsyncComponent(() => import('./sections/ManageAdmins.vue')),
};

const currentSection = computed(() => sections[activeSection.value]);

function onNavigate(section: string) {
    activeSection.value = section;
}
</script>

<template>
    <div class="flex flex-col gap-4 bg-[#fafcff]">
        <EdtsSubNav />

        <div class="flex gap-4">
            <EdtsSidebar
                v-model="activeSection"
                v-model:collapsed="sidebarCollapsed"
                :is-admin="isAdmin"
            />

            <main class="min-w-0 flex-1">
                <KeepAlive>
                    <component
                        :is="currentSection"
                        :documents="documents"
                        :departments="departments"
                        :doc-types="docTypes"
                        :is-admin="isAdmin"
                        :admin-departments="adminDepartments"
                        :admin-docs="adminDocs"
                        :metrics="metrics"
                        :all-users="allUsers"
                        @navigate="onNavigate"
                    />
                </KeepAlive>
            </main>
        </div>
    </div>
</template>
