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
            'estimated_cost' => data_get($this->resource, 'estimated_cost'),
            'estimated_min_cost' => data_get($this->resource, 'estimated_min_cost'),
            'estimated_max_cost' => data_get($this->resource, 'estimated_max_cost'),
            'currency' => 'USD',
        ];
    }
}
