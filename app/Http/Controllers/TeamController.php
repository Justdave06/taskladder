<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\ProjectInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhereHas('members', fn ($q2) => $q2->where('user_id', $user->id));
        });
        if (!$user->is_superadmin) {
            $projects->where('company_id', $user->company_id);
        }
        $projects = $projects->with(['members' => function ($q) {
            $q->with('user');
        }, 'creator'])->get();

        $allUsers = User::query();
        if (!$user->is_superadmin) {
            $allUsers->where('company_id', $user->company_id);
        }

        $allRoles = \App\Models\Role::query();
        if (!$user->is_superadmin) {
            $allRoles->where('company_id', $user->company_id);
        }

        return Inertia::render('taskladder/Team/Index', [
            'projects' => $projects,
            'allUsers' => $allUsers->get(['id', 'name', 'email']),
            'allRoles' => $allRoles->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $member = ProjectMember::firstOrCreate([
            'project_id' => $validated['project_id'],
            'user_id' => $validated['user_id'],
        ], [
            'role' => $validated['role'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
        ]);

        $member->user->notify(new ProjectInvitation($member->project, $member, $request->user()->name));

        return redirect()->back();
    }

    public function updateMember(Request $request, ProjectMember $member): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:accepted,declined,pending,completed'],
        ]);

        $member->update($validated);

        return redirect()->back();
    }

    public function removeMember(ProjectMember $member): RedirectResponse
    {
        $member->delete();

        return redirect()->back();
    }
}
