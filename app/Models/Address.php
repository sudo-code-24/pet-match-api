<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Address extends Model
{
    protected $fillable = [
        'street',
        'landmark',
        'barangay',
        'city',
        'province',
        'zip_code',
        'country',
        'latitude',
        'longitude',
        'full_address',
    ];

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserProfile::class);
    }

    public function shelters(): HasMany
    {
        return $this->hasMany(Shelter::class);
    }
}
