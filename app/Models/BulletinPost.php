<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulletinPost extends Model
{
    protected $fillable = [
        'user_id',
        'content',
        'image',
        'company_id',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'image' => 'string',
            'visibility' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(BulletinLike::class, 'bulletin_post_id');
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
