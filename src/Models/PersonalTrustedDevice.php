<?php

namespace DantePiazza\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PersonalTrustedDevice extends Model
{
    protected $fillable = [
        'verifyable_id',
        'verifyable_type',
        'fingerprint',
        'device_info',
        'is_trusted',
        'trusted_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_trusted' => 'boolean',
        'trusted_at' => 'datetime',
    ];

    public function verifyable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markAsTrusted(): void
    {
        $this->update([
            'is_trusted' => true,
            'trusted_at' => now(),
        ]);
    }
}