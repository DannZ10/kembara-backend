<?php

namespace App\Http\Requests\Gear;

use Illuminate\Foundation\Http\FormRequest;

class StoreGearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:gear_categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'brand' => 'nullable|string|max:100',
            'price_per_day' => 'required|numeric|min:0',
            'stock_total' => 'required|integer|min:1',
            'image_url' => 'nullable|string|url|max:255',
            'images' => 'nullable|array',
            'images.*' => 'string|url|max:255',
            'weight_kg' => 'nullable|numeric|min:0',
            'is_available' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori gear wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'name.required' => 'Nama gear wajib diisi.',
            'price_per_day.required' => 'Harga sewa per hari wajib diisi.',
            'price_per_day.min' => 'Harga sewa tidak boleh negatif.',
            'stock_total.required' => 'Jumlah stok total wajib diisi.',
            'stock_total.min' => 'Stok minimal 1 unit.',
        ];
    }
}
