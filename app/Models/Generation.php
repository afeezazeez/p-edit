<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'status',
    'pixelforge_generation_id',
    'idempotency_key',
    'estimated_cost',
    'provider_cost',
    'final_cost',
    'currency',
    'rates',
    'actual_usage',
    'image_url',
    'image_url_expires_at',
    'error_code',
    'error_message',
    'started_at',
    'completed_at',
])]
class Generation extends Model
{
    use HasUlids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'rates' => 'array',
            'actual_usage' => 'array',
            'status' => GenerationStatus::class,
            'estimated_cost' => 'decimal:11',
            'provider_cost' => 'decimal:11',
            'final_cost' => 'decimal:11',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
