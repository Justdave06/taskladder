<?php

namespace App\Http\Controllers;

use App\Models\BulletinPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulletinController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $posts = BulletinPost::with(['user', 'likes', 'user.company'])
            ->withCount('likes');

        if (!$user->is_superadmin) {
            $connectedCompanyIds = [];
            if ($user->company_id && $user->company) {
                $connectedCompanyIds = $user->company->connectedCompanies()->pluck('id')->toArray();
            }

            $posts->where(function ($q) use ($user, $connectedCompanyIds) {
                $q->where('company_id', $user->company_id)
                  ->orWhere(function ($q2) use ($connectedCompanyIds) {
                      $q2->where('visibility', 'public')
                          ->whereIn('company_id', $connectedCompanyIds);
                  });
            });
        }
        $posts = $posts->latest()->get()
            ->map(function ($post) use ($user) {
                $post->is_liked_by_me = $post->isLikedBy($user);
                return $post;
            });

        return Inertia::render('taskladder/Bulletin/Index', [
            'posts' => $posts,
            'can_post' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'image' => ['nullable', 'image', 'max:10240'],
            'visibility' => ['nullable', 'string', 'in:company,public'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bulletin-images', 'public');
        }

        BulletinPost::create([
            'user_id' => $user->id,
            'content' => $validated['content'],
            'image' => $imagePath,
            'company_id' => $user->company_id,
            'visibility' => $validated['visibility'] ?? 'company',
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

    public function destroy(Request $request, BulletinPost $post): RedirectResponse
    {
        if ($post->user_id !== $request->user()->id) {
            abort(403);
        }

        $post->delete();

        return redirect()->back();
    }
}
