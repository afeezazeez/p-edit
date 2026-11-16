<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class GenerationInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['sometimes', 'array', 'max:4'],
            'reference_images.*' => ['required', 'url:http,https'],
        ];
    }
}
