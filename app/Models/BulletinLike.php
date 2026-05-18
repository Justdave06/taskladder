<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinLike extends Model
{
    protected $fillable = [
        'bulletin_post_id',
        'user_id',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BulletinPost::class, 'bulletin_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
