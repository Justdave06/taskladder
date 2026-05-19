<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'shared_with',
    ];

    protected function casts(): array
    {
        return [
            'shared_with' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
