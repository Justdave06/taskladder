<?php

namespace App\Notifications;

use App\Models\TaskItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public TaskItem $task,
        public string $completedByName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'project_name' => $this->task->project?->title ?? 'Unknown',
            'completed_by' => $this->completedByName,
            'type' => 'task_completed',
        ];
    }
}
