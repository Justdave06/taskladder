<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_member_id',
        'project_id',
        'title',
        'description',
        'completion_notes',
        'deadline',
        'priority',
        'file_type',
        'file_path',
        'created_by',
        'status',
        'pinned',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'string',
            'file_type' => 'string',
            'status' => 'string',
            'pinned' => 'boolean',
            'deadline' => 'date',
        ];
    }

    public function projectMember(): BelongsTo
    {
        return $this->belongsTo(ProjectMember::class, 'project_member_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_id');
    }
}
