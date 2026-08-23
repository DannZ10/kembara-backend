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
            'items.*.gear_variant_id' => 'nullable|integer|exists:gear_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'delivery_type' => 'required|in:pickup,delivery',
            'delivery_address' => 'required_if:delivery_type,delivery|nullable|string|max:500',
            // Distance is derived server-side from the pasted Google Maps link.
            'delivery_maps_url' => 'required_if:delivery_type,delivery|nullable|string|max:1000',
            // Two guarantee IDs handed over at pickup/delivery (both required).
            'identity_type_1' => 'required|in:KTP,SIM,KTM,Paspor',
            'identity_type_2' => 'required|in:KTP,SIM,KTM,Paspor',
            'identity_agreed' => 'accepted',
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
            'delivery_maps_url.required_if' => 'Link Google Maps lokasi pengiriman wajib diisi jika memilih delivery.',
            'identity_type_1.required' => 'Pilih dokumen identitas pertama untuk jaminan.',
            'identity_type_1.in' => 'Identitas harus salah satu dari KTP, SIM, KTM, atau Paspor.',
            'identity_type_2.required' => 'Pilih dokumen identitas kedua untuk jaminan.',
            'identity_type_2.in' => 'Identitas harus salah satu dari KTP, SIM, KTM, atau Paspor.',
            'identity_agreed.accepted' => 'Anda harus menyetujui syarat jaminan identitas sebelum melanjutkan.',
        ];
    }
}
