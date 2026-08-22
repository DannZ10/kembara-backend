<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.gear_id' => 'required|exists:gears,id',
            'items.*.quantity' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'delivery_type' => 'required|in:pickup,delivery',
            'delivery_address' => 'required_if:delivery_type,delivery|nullable|string|max:500',
            'delivery_distance_km' => 'required_if:delivery_type,delivery|nullable|numeric|min:0.1|max:30',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal 1 gear untuk disewa.',
            'items.*.gear_id.exists' => 'Gear yang dipilih tidak ditemukan.',
            'items.*.quantity.min' => 'Jumlah unit gear minimal 1.',
            'start_date.after_or_equal' => 'Tanggal mulai sewa minimal hari ini.',
            'end_date.after' => 'Tanggal pengembalian harus setelah tanggal mulai sewa.',
            'delivery_type.required' => 'Pilih metode pengambilan (pickup atau delivery).',
            'delivery_address.required_if' => 'Alamat pengiriman wajib diisi jika memilih layanan delivery.',
            'delivery_distance_km.required_if' => 'Jarak pengiriman wajib diisi jika memilih layanan delivery.',
            'delivery_distance_km.max' => 'Jarak pengiriman maksimal 30 km.',
        ];
    }
}
