<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public Project $project,
        public ProjectMember $member,
        public string $invitedByName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->title,
            'invited_by' => $this->invitedByName,
            'member_id' => $this->member->id,
            'type' => 'project_invitation',
        ];
    }
}
