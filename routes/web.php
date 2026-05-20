<?php

use App\Http\Controllers\BulletinController;
use App\Http\Controllers\CompanyConnectController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\TaskItemController;
use App\Http\Controllers\TeamController;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskItem;
use Illuminate\Support\Facades\Broadcast;
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
    Route::post('tasks/{task}/file', [TaskItemController::class, 'uploadFile'])->name('tasks.file');
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
    Route::get('messages', [MessageController::class, 'globalIndex'])->name('messages.global');
    Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
    Route::patch('messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('messages/attachments', [MessageController::class, 'uploadAttachment'])->name('messages.attachments.upload');
    Route::post('messages/typing', [MessageController::class, 'typing'])->name('messages.typing');
    Route::get('messages/attachments/{attachment}/download', [MessageController::class, 'downloadAttachment'])->name('messages.attachments.download');
    Route::post('messages/{message}/reactions', [MessageController::class, 'toggleReaction'])->name('messages.reactions.toggle');
    Route::post('calls/signal', [MessageController::class, 'callSignal'])->name('calls.signal');
    Route::post('calls/missed', [MessageController::class, 'missedCall'])->name('calls.missed');

    // Bulletin
    Route::get('bulletin', [BulletinController::class, 'index'])->name('bulletin');
    Route::post('bulletin/posts', [BulletinController::class, 'store'])->name('bulletin.posts.store');
    Route::post('bulletin/posts/{post}/like', [BulletinController::class, 'toggleLike'])->name('bulletin.posts.like');
    Route::delete('bulletin/posts/{post}', [BulletinController::class, 'destroy'])->name('bulletin.posts.destroy');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('notifications/clear', [NotificationController::class, 'clearAll'])->name('notifications.clear');

    // Ledger
    Route::get('ledger', [LedgerController::class, 'index'])->name('ledger');
    Route::post('ledger', [LedgerController::class, 'store'])->name('ledger.store');
    Route::get('ledger/{ledger}', [LedgerController::class, 'show'])->name('ledger.show');
    Route::patch('ledger/{ledger}', [LedgerController::class, 'update'])->name('ledger.update');
    Route::delete('ledger/{ledger}', [LedgerController::class, 'destroy'])->name('ledger.destroy');
    Route::get('ledger/{ledger}/export', [LedgerController::class, 'export'])->name('ledger.export');
    Route::post('ledger/images', [LedgerController::class, 'uploadImage'])->name('ledger.images.upload');
    Route::post('ledger/{ledger}/send', [LedgerController::class, 'send'])->name('ledger.send');

    // Notes
    Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('notes/reorder', [NoteController::class, 'reorder'])->name('notes.reorder');

    // Company Connect
    Route::get('connect', [CompanyConnectController::class, 'index'])->name('connect');
    Route::get('connect/pending-count', [CompanyConnectController::class, 'pendingCount'])->name('connect.pending-count');
    Route::get('connect/company/{connectCode}', [CompanyConnectController::class, 'spectate'])->name('connect.spectate');
    Route::post('connect/send', [CompanyConnectController::class, 'send'])->name('connect.send');
    Route::post('connect/{connection}/accept', [CompanyConnectController::class, 'accept'])->name('connect.accept');
    Route::post('connect/{connection}/decline', [CompanyConnectController::class, 'decline'])->name('connect.decline');
    Route::delete('connect/{connection}', [CompanyConnectController::class, 'destroy'])->name('connect.destroy');

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

    // E-DTS Document Tracking
    Route::get('edts/pending-count', [\App\Http\Controllers\EdtsController::class, 'pendingCount'])->name('edts.pending-count');
    Route::post('edts/departments', [\App\Http\Controllers\EdtsController::class, 'storeDepartment'])->name('edts.departments.store');
    Route::put('edts/departments/{department}', [\App\Http\Controllers\EdtsController::class, 'updateDepartment'])->name('edts.departments.update');
    Route::delete('edts/departments/{department}', [\App\Http\Controllers\EdtsController::class, 'destroyDepartment'])->name('edts.departments.destroy');
    Route::post('edts/doc-types', [\App\Http\Controllers\EdtsController::class, 'storeDocType'])->name('edts.doc-types.store');
    Route::put('edts/doc-types/{docType}', [\App\Http\Controllers\EdtsController::class, 'updateDocType'])->name('edts.doc-types.update');
    Route::delete('edts/doc-types/{docType}', [\App\Http\Controllers\EdtsController::class, 'destroyDocType'])->name('edts.doc-types.destroy');
    Route::post('edts/admins', [\App\Http\Controllers\EdtsController::class, 'assignAdmin'])->name('edts.admins.assign');
    Route::delete('edts/admins/{admin}', [\App\Http\Controllers\EdtsController::class, 'removeAdmin'])->name('edts.admins.remove');
    Route::post('edts/{document}/receive', [\App\Http\Controllers\EdtsController::class, 'receive'])->name('edts.receive');
    Route::post('edts/{document}/forward', [\App\Http\Controllers\EdtsController::class, 'forward'])->name('edts.forward');
    Route::get('edts', [\App\Http\Controllers\EdtsController::class, 'index'])->name('edts');
    Route::post('edts', [\App\Http\Controllers\EdtsController::class, 'store'])->name('edts.store');
    Route::put('edts/{document}', [\App\Http\Controllers\EdtsController::class, 'update'])->name('edts.update');
    Route::get('edts/{referenceNumber}', [\App\Http\Controllers\EdtsController::class, 'show'])->name('edts.show')->where('referenceNumber', 'EDTS-.*');

    // Broadcasting auth
    Broadcast::routes();

    // Superadmin routes
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index'])->name('users');
        Route::post('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::get('/roles', [\App\Http\Controllers\Admin\AdminRoleController::class, 'index'])->name('roles');
        Route::post('/roles', [\App\Http\Controllers\Admin\AdminRoleController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [\App\Http\Controllers\Admin\AdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\Admin\AdminRoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('companies');
        Route::post('/companies', [\App\Http\Controllers\Admin\CompanyController::class, 'store'])->name('companies.store');
        Route::patch('/companies/{company}', [\App\Http\Controllers\Admin\CompanyController::class, 'update'])->name('companies.update');
        Route::delete('/companies/{company}', [\App\Http\Controllers\Admin\CompanyController::class, 'destroy'])->name('companies.destroy');
        Route::post('/companies/{company}/assign-admin', [\App\Http\Controllers\Admin\CompanyController::class, 'assignAdmin'])->name('companies.assign-admin');
    });
});

require __DIR__.'/settings.php';
