<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $reports = Ledger::where('user_id', $user->id)
            ->orWhereJsonContains('shared_with', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at', 'user_id', 'shared_with']);

        $users = User::where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('taskladder/Ledger/Index', [
            'reports' => $reports,
            'users' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $report = Ledger::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
        ]);

        return response()->json($report, 201);
    }

    public function update(Request $request, Ledger $ledger): JsonResponse
    {
        if ($ledger->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $ledger->update($validated);

        return response()->json($ledger);
    }

    public function show(Request $request, Ledger $ledger): JsonResponse
    {
        $user = $request->user();

        if ($ledger->user_id !== $user->id && !in_array($user->id, $ledger->shared_with ?? [])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($ledger);
    }

    public function destroy(Request $request, Ledger $ledger): JsonResponse
    {
        if ($ledger->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $ledger->delete();

        return response()->json(['ok' => true]);
    }

    public function export(Request $request, Ledger $ledger): HttpResponse
    {
        if ($ledger->user_id !== $request->user()->id) {
            abort(403);
        }

        $content = str_replace('<!--pb-->', '<div style="page-break-before: always; margin-top: 0;"></div>', $ledger->content);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12pt;
        line-height: 1.6;
        margin: 1in;
        color: #000;
    }
</style>
</head>
<body>
{$content}
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="' . $ledger->title . '.doc"',
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp,svg', 'max:5120'],
        ]);

        $path = $validated['image']->store('ledger/images', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function send(Request $request, Ledger $ledger): JsonResponse
    {
        if ($ledger->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $sharedWith = $ledger->shared_with ?? [];
        if (!in_array($validated['recipient_id'], $sharedWith)) {
            $sharedWith[] = $validated['recipient_id'];
            $ledger->update(['shared_with' => $sharedWith]);
        }

        $note = $validated['note'] ?: 'Shared report: ' . $ledger->title;

        $msg = Message::create([
            'recipient_id' => $validated['recipient_id'],
            'user_id' => $user->id,
            'content' => $note,
            'ledger_id' => $ledger->id,
        ]);

        $msg->load('user', 'attachments', 'reactions.user');

        broadcast(new MessageSent($msg));

        return response()->json($msg, 201);
    }
}
