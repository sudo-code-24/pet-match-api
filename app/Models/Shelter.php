<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shelter extends Model
{
    protected $fillable = [
        'user_id',
        'shelter_name',
        'description',
        'address',
        'address_id',
        'phone',
        'verification_status',
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
