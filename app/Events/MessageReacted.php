<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReacted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $senderId,
        public int $recipientId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->senderId),
            new PrivateChannel('App.Models.User.' . $this->recipientId),
        ];
    }

    public function broadcastWith(): array
    {
        $this->message->load('user', 'attachments', 'reactions.user');

        return [
            'id' => $this->message->id,
            'user_id' => $this->message->user_id,
            'recipient_id' => $this->message->recipient_id,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at,
            'updated_at' => $this->message->updated_at,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'email' => $this->message->user->email,
            ],
            'attachments' => $this->message->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_path' => $a->file_path,
                'mime_type' => $a->mime_type,
                'file_size' => $a->file_size,
                'url' => $a->url,
            ])->toArray(),
            'reactions' => $this->message->reactions->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'reaction' => $r->reaction,
                'user' => ['id' => $r->user->id, 'name' => $r->user->name],
            ])->toArray(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageReacted';
    }
}
