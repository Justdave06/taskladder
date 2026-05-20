<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdtsDepartmentAdmin extends Model
{
    protected $fillable = ['user_id', 'department_id'];

    protected $table = 'edts_department_admins';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(EdtsDepartment::class, 'department_id');
    }
}
