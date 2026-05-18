<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::allowIf(fn () => $request->user()->is_superadmin || $project->created_by === $request->user()->id);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $project->members()->firstOrCreate([
            'user_id' => $validated['user_id'],
        ]);

        return redirect()->back();
    }

    public function update(Request $request, ProjectMember $member): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,declined,completed'],
        ]);

        if ($validated['status'] === 'accepted' || $validated['status'] === 'declined') {
            if ($member->user_id !== $request->user()->id) {
                Gate::allowIf(fn () => $request->user()->is_superadmin);
            }

            $member->update([
                'status' => $validated['status'],
                'accepted_at' => $validated['status'] === 'accepted' ? now() : null,
            ]);
        }

        if ($validated['status'] === 'completed') {
            Gate::allowIf(fn () => $request->user()->is_superadmin || $member->project->created_by === $request->user()->id);
            $member->update(['status' => 'completed']);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, ProjectMember $member): RedirectResponse
    {
        Gate::allowIf(fn () => $request->user()->is_superadmin || $member->project->created_by === $request->user()->id);

        $member->delete();

        return redirect()->back();
    }
}
