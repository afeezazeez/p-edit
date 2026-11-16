<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenerationEstimateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'estimated_provider_cost' => data_get($this->resource, 'provider_cost'),
            'estimated_cost' => data_get($this->resource, 'estimated_cost'),
            'currency' => 'USD',
        ];
    }
}
