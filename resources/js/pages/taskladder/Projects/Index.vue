<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, ExternalLink, Users, ListChecks } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import projectsApi from '@/routes/projects';

interface ProjectMember {
    id: number;
    task_items: { is_completed: boolean }[];
}

interface Project {
    id: number;
    title: string;
    description: string | null;
    status: string;
    created_by: number;
    creator: { id: number; name: string };
    members_count: number;
    members: ProjectMember[];
    created_at: string;
}

defineProps<{
    projects: Project[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Projects',
                href: projectsApi.index(),
            },
        ],
    },
});

const page = usePage();
const currentUser = page.props.auth.user as unknown as { id: number; is_superadmin: boolean };

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const editingProject = ref<Project | null>(null);
const deletingProject = ref<Project | null>(null);
const form = ref({ title: '', description: '' });

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'secondary',
    active: 'default',
    completed: 'outline',
};

function resetForm(): void {
    form.value = { title: '', description: '' };
}

function openCreate(): void {
    resetForm();
    showCreateDialog.value = true;
}

function openEdit(project: Project): void {
    editingProject.value = project;
    form.value = { title: project.title, description: project.description ?? '' };
    showEditDialog.value = true;
}

function openDelete(project: Project): void {
    deletingProject.value = project;
    showDeleteDialog.value = true;
}

function createProject(): void {
    router.post(projectsApi.store.url(), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateDialog.value = false;
            resetForm();
        },
    });
}

function updateProject(): void {
    if (!editingProject.value) {
 return; 
}

    router.put(projectsApi.update.url({ project: editingProject.value.id }), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            showEditDialog.value = false;
            editingProject.value = null;
            resetForm();
        },
    });
}

function deleteProject(): void {
    if (!deletingProject.value) {
 return; 
}

    router.delete(projectsApi.destroy.url({ project: deletingProject.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            deletingProject.value = null;
        },
    });
}

function totalTasks(members: ProjectMember[]): number {
    return members.reduce((sum, m) => sum + (m.task_items?.length ?? 0), 0);
}

function completedTasks(members: ProjectMember[]): number {
    return members.reduce(
        (sum, m) => sum + (m.task_items?.filter((t) => t.is_completed).length ?? 0),
        0,
    );
}

function canManage(project: Project): boolean {
    return currentUser.is_superadmin || project.created_by === currentUser.id;
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading title="Projects" description="Manage your projects and teams" />
            <Button @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                New Project
            </Button>
        </div>

        <div v-if="projects.length === 0" class="flex flex-col items-center gap-4 py-16 text-muted-foreground">
            <ListChecks class="h-16 w-16" />
            <p class="text-lg">No projects yet</p>
            <p>Create your first project to get started</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="project in projects" :key="project.id">
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <CardTitle class="truncate">{{ project.title }}</CardTitle>
                            <CardDescription class="truncate">
                                {{ project.creator.name }}
                            </CardDescription>
                        </div>
                        <Badge :variant="statusVariants[project.status] ?? 'secondary'">
                            {{ project.status }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <p v-if="project.description" class="mb-4 line-clamp-2 text-sm text-muted-foreground">
                        {{ project.description }}
                    </p>
                    <div class="flex items-center gap-4 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1">
                            <Users class="h-4 w-4" />
                            {{ project.members_count }}
                        </span>
                        <span class="flex items-center gap-1">
                            <ListChecks class="h-4 w-4" />
                            {{ completedTasks(project.members) }}/{{ totalTasks(project.members) }}
                        </span>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="projectsApi.show({ project: project.id })">
                                <ExternalLink class="mr-1 h-3 w-3" />
                                Open
                            </Link>
                        </Button>
                        <Button
                            v-if="canManage(project)"
                            variant="outline"
                            size="sm"
                            @click="openEdit(project)"
                        >
                            <Pencil class="h-3 w-3" />
                        </Button>
                        <Button
                            v-if="currentUser.is_superadmin"
                            variant="outline"
                            size="sm"
                            class="text-destructive"
                            @click="openDelete(project)"
                        >
                            <Trash2 class="h-3 w-3" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Project</DialogTitle>
                    <DialogDescription>Create a new project to manage tasks and team members.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="createProject" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" placeholder="Project title" required />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="form.description" placeholder="Optional description" />
                    </div>
                    <Button type="submit">Create</Button>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Project</DialogTitle>
                    <DialogDescription>Update the project details.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="updateProject" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="edit-title">Title</Label>
                        <Input id="edit-title" v-model="form.title" placeholder="Project title" required />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="edit-description">Description</Label>
                        <Input id="edit-description" v-model="form.description" placeholder="Optional description" />
                    </div>
                    <Button type="submit">Save</Button>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Project</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ deletingProject?.title }}"? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="deleteProject">Delete</Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
