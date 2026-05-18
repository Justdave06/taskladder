<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCheck, Bell, BellDot, ThumbsUp, ThumbsDown, CheckCircle2, UserPlus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import notifications from '@/routes/notifications';
import tasks from '@/routes/tasks';

interface NotificationData {
    id: string;
    type: string;
    data: {
        task_id?: number;
        task_title?: string;
        project_name?: string;
        assigned_by?: string;
        completed_by?: string;
        project_id?: number;
        member_id?: number;
        invited_by?: string;
        type?: string;
        title?: string;
        body?: string;
    };
    read_at: string | null;
    created_at: string;
}

defineProps<{
    notificationsData: {
        data: NotificationData[];
        meta: { total: number; current_page: number; last_page: number };
    };
    unreadCount: number;
}>();

function markAsRead(id: string) {
    router.post(notifications.read.url({ id }), {}, { preserveScroll: true });
}

function markAllAsRead() {
    router.post(notifications.readAll.url(), {}, { preserveScroll: true });
}

function clearAll() {
    router.delete(notifications.clear.url(), { preserveScroll: true });
}

function acceptTask(taskId: number, notifId: string) {
    router.post(tasks.accept.url({ task: taskId }), {}, {
        preserveScroll: true,
        onSuccess: () => markAsRead(notifId),
    });
}

function declineTask(taskId: number, notifId: string) {
    router.post(tasks.decline.url({ task: taskId }), {}, {
        preserveScroll: true,
        onSuccess: () => markAsRead(notifId),
    });
}

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

function isTaskAssigned(notification: NotificationData): boolean {
    return notification.data?.type === 'task_assigned';
}

function isTaskCompleted(notification: NotificationData): boolean {
    return notification.data?.type === 'task_completed';
}

function isProjectInvitation(notification: NotificationData): boolean {
    return notification.data?.type === 'project_invitation';
}

function acceptInvitation(memberId: number, notifId: string) {
    router.patch(`/projects/members/${memberId}`, { status: 'accepted' }, {
        preserveScroll: true,
        onSuccess: () => markAsRead(notifId),
    });
}

function declineInvitation(memberId: number, notifId: string) {
    router.patch(`/projects/members/${memberId}`, { status: 'declined' }, {
        preserveScroll: true,
        onSuccess: () => markAsRead(notifId),
    });
}

function notificationTitle(notification: NotificationData): string {
    if (isTaskAssigned(notification)) {
        return `Task assigned: ${notification.data.task_title || ''}`;
    }

    if (isTaskCompleted(notification)) {
        return `Task completed: ${notification.data.task_title || ''}`;
    }

    if (isProjectInvitation(notification)) {
        return `Project invitation: ${notification.data.project_name || ''}`;
    }

    return (notification.data?.title as string) || 'Notification';
}

function notificationBody(notification: NotificationData): string {
    if (isTaskAssigned(notification)) {
        return `Assigned by ${notification.data.assigned_by || 'the boss'} — ${notification.data.project_name || ''}`;
    }

    if (isTaskCompleted(notification)) {
        return `Completed by ${notification.data.completed_by || 'a member'} — ${notification.data.project_name || ''}`;
    }

    if (isProjectInvitation(notification)) {
        return `Invited by ${notification.data.invited_by || 'the boss'} to join ${notification.data.project_name || ''}`;
    }

    return (notification.data?.body as string) || '';
}
</script>

<template>
    <Head title="Notifications" />

    <div class="mx-[40px] py-4">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold">Notifications</h2>
                <Badge v-if="unreadCount > 0" class="text-[10px]">{{ unreadCount }}</Badge>
            </div>
            <div class="flex gap-2">
                <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllAsRead">
                    <CheckCheck class="mr-1 h-4 w-4" />
                    Mark all read
                </Button>
                <Button v-if="notificationsData.data.length > 0" variant="outline" size="sm" class="border-red-200 text-red-600 hover:bg-red-50" @click="clearAll">
                    <Trash2 class="mr-1 h-4 w-4" />
                    Clear
                </Button>
            </div>
        </div>

        <div v-if="notificationsData.data.length === 0" class="py-12 text-center text-sm text-muted-foreground">
            <Bell class="mx-auto mb-2 h-8 w-8" />
            No notifications yet.
        </div>

        <div v-else class="flex flex-col gap-2">
            <div
                v-for="notification in notificationsData.data"
                :key="notification.id"
                class="flex items-start gap-3 rounded-lg border px-4 py-3 transition-colors"
                :class="notification.read_at ? '' : 'bg-muted/30'"
            >
                <CheckCircle2 v-if="isTaskCompleted(notification)" class="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                <UserPlus v-else-if="isProjectInvitation(notification)" class="mt-0.5 h-4 w-4 shrink-0 text-purple-500" />
                <BellDot v-else-if="!notification.read_at" class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
                <Bell v-else class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                <div class="flex-1">
                    <div class="text-sm" :class="notification.read_at ? 'text-muted-foreground' : 'font-medium'">
                        {{ notificationTitle(notification) }}
                    </div>
                    <p v-if="notificationBody(notification)" class="text-xs text-muted-foreground">
                        {{ notificationBody(notification) }}
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ timeAgo(notification.created_at) }}</p>

                    <!-- Accept/Decline for project invitation notifications -->
                    <div v-if="!notification.read_at && isProjectInvitation(notification) && notification.data.member_id" class="mt-2 flex gap-2">
                        <Button
                            size="sm"
                            class="bg-green-600 text-white hover:bg-green-700"
                            @click="acceptInvitation(notification.data.member_id!, notification.id)"
                        >
                            <ThumbsUp class="mr-1 h-3 w-3" /> Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="border-red-200 text-red-600 hover:bg-red-50"
                            @click="declineInvitation(notification.data.member_id!, notification.id)"
                        >
                            <ThumbsDown class="mr-1 h-3 w-3" /> Decline
                        </Button>
                    </div>
                    <!-- Accept/Decline for task assignment notifications -->
                    <div v-if="!notification.read_at && isTaskAssigned(notification) && notification.data.task_id" class="mt-2 flex gap-2">
                        <Button
                            size="sm"
                            class="bg-green-600 text-white hover:bg-green-700"
                            @click="acceptTask(notification.data.task_id!, notification.id)"
                        >
                            <ThumbsUp class="mr-1 h-3 w-3" /> Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="border-red-200 text-red-600 hover:bg-red-50"
                            @click="declineTask(notification.data.task_id!, notification.id)"
                        >
                            <ThumbsDown class="mr-1 h-3 w-3" /> Decline
                        </Button>
                    </div>
                </div>
                <button
                    v-if="!notification.read_at && !isTaskAssigned(notification) && !isProjectInvitation(notification)"
                    class="shrink-0 text-xs text-blue-600 hover:underline"
                    @click="markAsRead(notification.id)"
                >
                    Read
                </button>
            </div>
        </div>
    </div>
</template>
