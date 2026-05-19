<?php

namespace App\Http\Controllers;

use App\Events\CallEvent;
use App\Events\MessageReacted;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Company;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MessageController extends Controller
{
    public function globalIndex(Request $request)
    {
        $user = $request->user();

        if ($user->is_superadmin) {
            $contacts = User::where('is_company_admin', true)
                ->where('id', '!=', $user->id)
                ->with('company')
                ->get();
        } elseif ($user->is_company_admin) {
            $sameCompany = User::where('id', '!=', $user->id)
                ->where('company_id', $user->company_id)
                ->with('company')
                ->get();
            $superadmin = User::where('is_superadmin', true)
                ->with('company')
                ->get();
            $contacts = $sameCompany->concat($superadmin);
        } else {
            $contacts = User::where('id', '!=', $user->id)
                ->where('company_id', $user->company_id)
                ->with('company')
                ->get();
        }

        // Include connected company admins for users with a company
        if ($user->company_id) {
            $myCompany = $user->company;
            $connectedCompanies = $myCompany->connectedCompanies();
            $connectedUsers = User::whereIn('company_id', $connectedCompanies->pluck('id'))
                ->where('is_company_admin', true)
                ->with('company')
                ->get();
            $contacts = $contacts->concat($connectedUsers);
        }

        $messages = Message::with('user', 'attachments', 'reactions.user')
            ->where('user_id', $user->id)
            ->orWhere('recipient_id', $user->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('taskladder/Messages/Index', [
            'contacts' => $contacts,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'required_without:attachment_ids', 'string', 'max:5000'],
            'recipient_id' => ['required', 'exists:users,id'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['exists:message_attachments,id'],
        ]);

        $user = $request->user();

        $msg = Message::create([
            'recipient_id' => $validated['recipient_id'],
            'user_id' => $user->id,
            'content' => $validated['content'] ?? '',
        ]);

        if (!empty($validated['attachment_ids'])) {
            MessageAttachment::whereIn('id', $validated['attachment_ids'])
                ->where('user_id', $user->id)
                ->whereNull('message_id')
                ->update(['message_id' => $msg->id]);
        }

        $msg->load('user', 'attachments', 'reactions.user');

        broadcast(new MessageSent($msg));

        return response()->json($msg, 201);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        if ($message->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message->update(['content' => $validated['content']]);
        $message->load('user', 'attachments', 'reactions.user');

        broadcast(new MessageSent($message));

        return response()->json($message);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        if ($message->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $message->delete();

        return response()->json(['deleted' => true]);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $user = $request->user();
        $file = $validated['file'];
        $path = $file->store('messages', 'public');

        $attachment = MessageAttachment::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json($attachment->toArray() + ['url' => $attachment->url], 201);
    }

    public function typing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'typing' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        broadcast(new UserTyping(
            senderId: $user->id,
            senderName: $user->name,
            recipientId: $validated['recipient_id'],
            typing: $validated['typing'],
        ));

        return response()->json(['ok' => true]);
    }

    public function downloadAttachment(MessageAttachment $attachment): BinaryFileResponse
    {
        $path = storage_path('app/public/' . $attachment->file_path);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $attachment->file_name);
    }

    public function toggleReaction(Request $request, Message $message): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $reaction = $validated['reaction'];

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('reaction', $reaction)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'reaction' => $reaction,
            ]);
        }

        $message->load('user', 'attachments', 'reactions.user');

        $senderId = $message->user_id;
        $recipientId = $message->recipient_id;

        broadcast(new MessageReacted($message, $senderId, $recipientId ?? $senderId));

        return response()->json($message);
    }

    public function callSignal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'string', 'max:20'],
            'data' => ['nullable'],
        ]);

        $user = $request->user();

        broadcast(new CallEvent(
            senderId: $user->id,
            senderName: $user->name,
            recipientId: $validated['recipient_id'],
            type: $validated['type'],
            data: $validated['data'],
        ));

        return response()->json(['ok' => true]);
    }

    public function missedCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
        ]);

        $user = $request->user();

        $msg = Message::create([
            'recipient_id' => $validated['recipient_id'],
            'user_id' => $user->id,
            'content' => 'Missed call',
            'type' => 'missed_call',
        ]);

        $msg->load('user', 'attachments', 'reactions.user');

        broadcast(new MessageSent($msg));

        return response()->json($msg, 201);
    }
}
