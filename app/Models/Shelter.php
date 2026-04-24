<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Shelter extends Model
{
    protected $fillable = [
        'user_id',
        'organization_name',
        'website',
        'ein_tax_id',
        'physical_address',
        'bio_mission',
        'logo',
        'verification_docs',
        'shelter_type',
        'max_capacity',
        'facilities',
        'operating_hours',
        'services_offered',
        'adoption_requirements',
        'shelter_name',
        'description',
        'address',
        'address_id',
        'phone',
        'verification_status',
    ];

    protected $casts = [
        'verification_docs' => 'array',
        'facilities' => 'array',
        'operating_hours' => 'array',
        'services_offered' => 'array',
        'max_capacity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addressRecord(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
}
