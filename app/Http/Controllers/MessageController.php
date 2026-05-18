<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Project $project)
    {
        return Message::where('project_id', $project->id)
            ->with('user')
            ->latest()
            ->get();
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $msg = Message::create([
            'project_id' => $validated['project_id'],
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $msg->load('user');

        if ($request->wantsJson()) {
            return response()->json($msg, 201);
        }

        return redirect()->back();
    }
}
