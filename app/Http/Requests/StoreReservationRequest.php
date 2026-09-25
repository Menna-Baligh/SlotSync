<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreReservationRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'units' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date', 'after_or_equal:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'resource_id.required' => 'The resource ID is required.',
            'resource_id.exists' => 'The selected resource does not exist.',
            'units.required' => 'The units field is required.',
            'units.min' => 'Units must be at least 1.',
            'start_time.required' => 'The start time is required.',
            'start_time.after_or_equal' => 'Start time cannot be in the past.',
            'end_time.required' => 'The end time is required.',
            'end_time.after' => 'End time must be strictly after the start time.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            $this->errorResponse(
                message: 'Validation failed. Please check your inputs.',
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $validator->errors()
            )
        );
    }
}
