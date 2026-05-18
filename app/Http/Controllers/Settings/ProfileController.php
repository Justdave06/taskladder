<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Project;
use App\Models\TaskItem;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $userId = $user->id;

        $projects = Project::where('created_by', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->with(['members' => fn ($q) => $q->where('user_id', $userId)])
            ->get(['id', 'title', 'created_by']);

        $memberIds = \App\Models\ProjectMember::where('user_id', $userId)
            ->whereIn('project_id', $projects->pluck('id'))
            ->pluck('id');

        $taskStats = [
            'total_assigned' => TaskItem::whereIn('assigned_member_id', $memberIds)->count(),
            'completed' => TaskItem::whereIn('assigned_member_id', $memberIds)->where('status', 'completed')->count(),
            'pending' => TaskItem::whereIn('assigned_member_id', $memberIds)->where('status', 'accepted')->count(),
        ];

        $recentTasks = TaskItem::whereIn('assigned_member_id', $memberIds)
            ->with(['projectMember.user'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'projects' => $projects,
            'taskStats' => $taskStats,
            'recentTasks' => $recentTasks,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
