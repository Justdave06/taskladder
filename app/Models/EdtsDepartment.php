<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdtsDepartment extends Model
{
    protected $fillable = ['name', 'description'];

    public function admins()
    {
        return $this->hasMany(EdtsDepartmentAdmin::class, 'department_id');
    }

    public function documents()
    {
        return $this->hasMany(EdtsDocument::class, 'department_from_id');
    }
}
