<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdtsDocument extends Model
{
    protected $fillable = [
        'reference_number',
        'user_id',
        'department_from_id',
        'requester_name',
        'requester_email',
        'requester_phone',
        'title',
        'type',
        'recipient_office',
        'purpose',
        'priority',
        'notes',
        'doc_type_other',
        'status',
        'assigned_to_id',
        'forwarded_to_id',
        'department_to_id',
        'forwarded_from_id',
        'received_at',
        'reviewed_at',
        'completed_at',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departmentFrom()
    {
        return $this->belongsTo(EdtsDepartment::class, 'department_from_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function forwardedTo()
    {
        return $this->belongsTo(User::class, 'forwarded_to_id');
    }

    public function departmentTo()
    {
        return $this->belongsTo(EdtsDepartment::class, 'department_to_id');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(EdtsDepartment::class, 'forwarded_from_id');
    }

    public function docTypes()
    {
        return $this->belongsToMany(EdtsDocType::class, 'edts_document_doc_type', 'edts_document_id', 'edts_doc_type_id');
    }
}
