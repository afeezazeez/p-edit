<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminCostReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $start = $this->input('start_date');
            $end = $this->input('end_date');
            if ($start && $end && $end < $start) {
                $validator->errors()->add('end_date', 'End date must be on or after the start date.');
            }
        }];
    }
}
