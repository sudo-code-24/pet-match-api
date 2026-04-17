<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'last_name_updated_at',
        'avatar_url',
        'bio',
        'address_id',
        'is_discoverable',
    ];

    protected $casts = [
        'last_name_updated_at' => 'datetime',
        'is_discoverable' => 'boolean',
    ];

    protected $attributes = [
        'is_discoverable' => true,
    ];

    /**
     * Scope to only include profiles that are discoverable by nearby users.
     * All discovery queries MUST apply this scope to respect the user's
     * visibility preference.
     */
    public function scopeDiscoverable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_discoverable', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }
}
