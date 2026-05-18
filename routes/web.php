<?php

use App\Http\Controllers\BulletinController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\TaskItemController;
use App\Http\Controllers\TeamController;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskItem;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalProjects' => Project::count(),
                'activeProjects' => Project::where('status', 'active')->count(),
                'pendingInvites' => ProjectMember::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'myCompletedTasks' => TaskItem::whereHas('checklistItems', fn ($q) => $q->where('is_completed', true))
                    ->whereHas('projectMember', fn ($q) => $q->where('user_id', $user->id))
                    ->count(),
                'myPendingTasks' => TaskItem::whereDoesntHave('checklistItems', fn ($q) => $q->where('is_completed', true))
                    ->whereHas('projectMember', fn ($q) => $q->where('user_id', $user->id))
                    ->count(),
            ],
            'recentProjects' => Project::with('creator')
                ->withCount('members')
                ->latest()
                ->take(5)
                ->get(),
            'pendingInvites' => ProjectMember::with(['project', 'project.creator'])
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->get(),
        ]);
    })->name('dashboard');

    // Board
    Route::get('board', [TaskItemController::class, 'board'])->name('board');

    // Tasks
    Route::get('tasks', [TaskItemController::class, 'list'])->name('tasks');
    Route::get('tasks/{task}/view', [TaskItemController::class, 'show'])->name('tasks.show');
    Route::post('tasks', [TaskItemController::class, 'store'])->name('tasks.store');
    Route::post('tasks/{task}/assign', [TaskItemController::class, 'assign'])->name('tasks.assign');
    Route::post('tasks/{task}/unassign', [TaskItemController::class, 'unassign'])->name('tasks.unassign');
    Route::post('tasks/{task}/accept', [TaskItemController::class, 'accept'])->name('tasks.accept');
    Route::post('tasks/{task}/decline', [TaskItemController::class, 'decline'])->name('tasks.decline');
    Route::post('tasks/{task}/done', [TaskItemController::class, 'done'])->name('tasks.done');
    Route::patch('checklist-items/{item}', [TaskItemController::class, 'toggleChecklist'])->name('checklist-items.toggle');
    Route::post('tasks/{task}/pin', [TaskItemController::class, 'togglePin'])->name('tasks.pin');
    Route::delete('tasks/{task}', [TaskItemController::class, 'destroy'])->name('tasks.destroy');

    // Team
    Route::get('team', [TeamController::class, 'index'])->name('team');
    Route::post('team/invite', [TeamController::class, 'invite'])->name('team.invite');
    Route::patch('team/members/{member}', [TeamController::class, 'updateMember'])->name('team.members.update');
    Route::delete('team/members/{member}', [TeamController::class, 'removeMember'])->name('team.members.remove');

    // Manage (projects, users, roles)
    Route::get('manage', [ManageController::class, 'index'])->name('manage');
    Route::post('manage/projects', [ManageController::class, 'storeProject'])->name('manage.projects.store');
    Route::delete('manage/projects/{project}', [ManageController::class, 'destroyProject'])->name('manage.projects.destroy');
    Route::post('manage/users', [ManageController::class, 'storeUser'])->name('manage.users.store');
    Route::patch('manage/members/{member}', [ManageController::class, 'updateMember'])->name('manage.members.update');
    Route::delete('manage/members/{member}', [ManageController::class, 'removeMember'])->name('manage.members.remove');
    Route::post('manage/roles', [ManageController::class, 'storeRole'])->name('manage.roles.store');
    Route::delete('manage/roles/{role}', [ManageController::class, 'destroyRole'])->name('manage.roles.destroy');
    Route::patch('manage/members/{member}/role', [ManageController::class, 'assignRole'])->name('manage.members.role');

    // Messages
    Route::get('messages/{project}', [MessageController::class, 'index'])->name('messages.index');
    Route::post('messages', [MessageController::class, 'store'])->name('messages.store');

    // Bulletin
    Route::get('bulletin', [BulletinController::class, 'index'])->name('bulletin');
    Route::post('bulletin/posts', [BulletinController::class, 'store'])->name('bulletin.posts.store');
    Route::post('bulletin/posts/{post}/like', [BulletinController::class, 'toggleLike'])->name('bulletin.posts.like');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('notifications/clear', [NotificationController::class, 'clearAll'])->name('notifications.clear');

    // Notes
    Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('notes/reorder', [NoteController::class, 'reorder'])->name('notes.reorder');

    // Legacy project routes (accessible but not in main nav)
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::patch('projects/members/{member}', [ProjectMemberController::class, 'update'])->name('projects.members.update');
    Route::delete('projects/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

    Route::post('projects/members/{member}/tasks', [TaskItemController::class, 'store'])->name('projects.members.tasks.store');
    Route::patch('tasks/{task}', [TaskItemController::class, 'update'])->name('tasks.update');
});

require __DIR__.'/settings.php';
