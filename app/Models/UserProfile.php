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
        'avatar_url',
        'bio',
        'address_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }
}
