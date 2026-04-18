<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Conversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_one_id',
        'user_two_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Latest message per conversation.
     *
     * Do not use {@see \Illuminate\Database\Eloquent\Relations\Concerns\CanBeOneOfMany::latestOfMany}
     * here: Laravel still applies MAX(primary key) for ties, and PostgreSQL has no MAX(uuid).
     * A correlated subquery orders by {@see Message::$created_at} only.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)
            ->whereRaw(
                'messages.id = (
                    select m2.id from messages m2
                    where m2.conversation_id = messages.conversation_id
                    order by m2.created_at desc nulls last, m2.id desc
                    limit 1
                )',
            );
    }
}
