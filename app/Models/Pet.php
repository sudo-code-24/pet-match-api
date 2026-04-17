<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Illuminate\Database\Eloquent\Builder;

#[TypeScript]
class Pet extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'species',
        'gender',
        'breed',
        'age',
        'health_notes',
        'adoption_details',
        'purpose',
        'image_url',
        'image_urls',
        'active',

    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'active' => 'boolean',
            'image_urls' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active_records', static function (Builder $query): void {
            $query->where('active', true);
        });

        static::creating(function (self $pet): void {
            if (empty($pet->id)) {
                $pet->id = (string) Str::uuid();
            }
        });
    }
}
