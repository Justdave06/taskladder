<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FolderKanban,
    FolderCheck,
    Mail,
    ListChecks,
    Check,
    X,
    Users,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
import members from '@/routes/projects/members';

interface Stats {
    totalProjects: number;
    activeProjects: number;
    pendingInvites: number;
    myCompletedTasks: number;
    myPendingTasks: number;
}

interface Project {
    id: number;
    title: string;
    status: string;
    creator: { id: number; name: string };
    members_count: number;
    created_at: string;
}

interface PendingInvite {
    id: number;
    project_id: number;
    status: string;
    project: {
        id: number;
        title: string;
        creator: { id: number; name: string };
    };
}

defineProps<{
    stats: Stats;
    recentProjects: Project[];
    pendingInvites: PendingInvite[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'secondary',
    active: 'default',
    completed: 'outline',
};

function acceptInvite(memberId: number) {
    router.patch(members.update.url({ member: memberId }), { status: 'accepted' }, {
        preserveScroll: true,
    });
}

function declineInvite(memberId: number) {
    router.patch(members.update.url({ member: memberId }), { status: 'declined' }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <Heading title="Dashboard" description="Overview of your projects and tasks" />

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Total Projects</CardTitle>
                    <FolderKanban class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.totalProjects }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Active Projects</CardTitle>
                    <FolderCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.activeProjects }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Pending Invites</CardTitle>
                    <Mail class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.pendingInvites }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">My Tasks</CardTitle>
                    <ListChecks class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold">{{ stats.myCompletedTasks }}</span>
                        <span class="text-sm text-muted-foreground">
                            / {{ stats.myCompletedTasks + stats.myPendingTasks }} done
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Recent Projects</CardTitle>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="projects.index.url()">View All</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="recentProjects.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        No projects yet
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="project in recentProjects"
                            :key="project.id"
                            class="flex items-center justify-between rounded-md border px-3 py-2"
                        >
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="projects.show({ project: project.id })"
                                    class="text-sm font-medium hover:underline"
                                >
                                    {{ project.title }}
                                </Link>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span>{{ project.creator.name }}</span>
                                    <span>·</span>
                                    <span class="flex items-center gap-1">
                                        <Users class="h-3 w-3" />
                                        {{ project.members_count }}
                                    </span>
                                </div>
                            </div>
                            <Badge :variant="statusVariants[project.status] ?? 'secondary'">
                                {{ project.status }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Pending Invitations</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="pendingInvites.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                        No pending invitations
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="invite in pendingInvites"
                            :key="invite.id"
                            class="rounded-md border px-3 py-2"
                        >
                            <div class="text-sm font-medium">{{ invite.project.title }}</div>
                            <div class="mb-2 text-xs text-muted-foreground">
                                Invited by {{ invite.project.creator.name }}
                            </div>
                            <div class="flex gap-2">
                                <Button size="sm" @click="acceptInvite(invite.id)">
                                    <Check class="mr-1 h-3 w-3" />
                                    Accept
                                </Button>
                                <Button size="sm" variant="outline" @click="declineInvite(invite.id)">
                                    <X class="mr-1 h-3 w-3" />
                                    Decline
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
