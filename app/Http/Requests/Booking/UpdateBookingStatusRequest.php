<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:confirmed,active,returned,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status booking wajib diisi.',
            'status.in' => 'Status booking tidak valid.',
        ];
    }
}
