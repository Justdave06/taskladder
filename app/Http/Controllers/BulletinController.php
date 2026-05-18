<?php

namespace App\Http\Controllers;

use App\Models\BulletinPost;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulletinController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $posts = BulletinPost::with(['user', 'likes'])
            ->withCount('likes')
            ->latest()
            ->get()
            ->map(function ($post) use ($user) {
                $post->is_liked_by_me = $post->isLikedBy($user);
                return $post;
            });

        $projects = Project::where('created_by', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->get(['id', 'title', 'created_by']);

        $isBoss = $projects->contains('created_by', $user->id);

        return Inertia::render('taskladder/Bulletin/Index', [
            'posts' => $posts,
            'projects' => $projects,
            'is_boss' => $isBoss,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $firstProject = Project::where('created_by', $user->id)->first();
        if (!$firstProject) {
            abort(403, 'You must create a project before posting to the bulletin.');
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'image' => ['nullable', 'image', 'max:10240'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bulletin-images', 'public');
        }

        BulletinPost::create([
            'project_id' => $firstProject->id,
            'user_id' => $user->id,
            'content' => $validated['content'],
            'image' => $imagePath,
        ]);

        return redirect()->back();
    }

    public function toggleLike(Request $request, BulletinPost $post): RedirectResponse
    {
        $user = $request->user();

        $like = $post->likes()->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();
        } else {
            $post->likes()->create(['user_id' => $user->id]);
        }

        return redirect()->back();
    }
}
