<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class StoreGenerationRequest extends GenerationInputRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'idempotency_key' => ['nullable', 'uuid']];
    }

    public function idempotencyKey(): string
    {
        return $this->validated('idempotency_key') ?? (string) Str::uuid();
    }
}
