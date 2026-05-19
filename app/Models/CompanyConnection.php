<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyConnection extends Model
{
    protected $fillable = [
        'from_company_id',
        'to_company_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }
}
