<?php

namespace App\Http\Requests\Gear;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|required|exists:gear_categories,id',
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'price_per_day' => 'sometimes|required|numeric|min:0',
            'stock_total' => 'sometimes|required|integer|min:0',
            'stock_available' => 'sometimes|required|integer|min:0',
            'image_url' => 'nullable|string|url|max:255',
            'images' => 'nullable|array',
            'images.*' => 'string|url|max:255',
            'weight_kg' => 'nullable|numeric|min:0',
            'is_available' => 'nullable|boolean',
        ];
    }
}
