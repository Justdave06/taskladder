<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'color',
        'connect_code',
        'created_by',
    ];

    protected $appends = ['logo_url'];

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            if (!$company->connect_code) {
                $company->connect_code = strtoupper(Str::random(8));
            }
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sentConnections(): HasMany
    {
        return $this->hasMany(CompanyConnection::class, 'from_company_id');
    }

    public function receivedConnections(): HasMany
    {
        return $this->hasMany(CompanyConnection::class, 'to_company_id');
    }

    public function connectedCompanies()
    {
        $sentIds = $this->sentConnections()
            ->where('status', 'accepted')
            ->pluck('to_company_id');

        $receivedIds = $this->receivedConnections()
            ->where('status', 'accepted')
            ->pluck('from_company_id');

        return static::whereIn('id', $sentIds->merge($receivedIds));
    }

    public function isConnectedTo(self $other): bool
    {
        return $this->sentConnections()
            ->where('to_company_id', $other->id)
            ->where('status', 'accepted')
            ->exists()
            || $this->receivedConnections()
            ->where('from_company_id', $other->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function connectionStatusWith(self $other): ?string
    {
        $sent = $this->sentConnections()->where('to_company_id', $other->id)->first();
        if ($sent) return $sent->status;

        $received = $this->receivedConnections()->where('from_company_id', $other->id)->first();
        if ($received) return 'received_' . $received->status;

        return null;
    }
}
