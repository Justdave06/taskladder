<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TaskChecklistItem;

class ProjectMember extends Model
{
    use HasFactory;

    protected $table = 'project_member';

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'role',
        'role_id',
        'accepted_at',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'role' => 'string',
            'accepted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskItems(): HasMany
    {
        return $this->hasMany(TaskItem::class, 'project_member_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(TaskItem::class, 'project_member_id');
    }

    public function getProgressAttribute(): int
    {
        $total = $this->taskItems()->withCount('checklistItems')->get()->sum('checklist_items_count');
        if ($total === 0) {
            return 0;
        }

        $completed = TaskChecklistItem::whereHas('task', fn ($q) => $q->where('project_member_id', $this->id))
            ->where('is_completed', true)
            ->count();

        return (int) round(($completed / $total) * 100);
    }
}
