<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OneChargingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'one_chargings' => ['required', 'array'],
            'one_chargings.*' => ['distinct'],
            'one_chargings.*.id' => ['required', 'distinct'],
            'one_chargings.*.sync_id' => ['required', 'distinct'],
            'one_chargings.*.code' => ['required', 'distinct'],
            'one_chargings.*.name' => ['required'],
            'one_chargings.*.updated_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'one_chargings.*.deleted_at' => ['nullable', 'date_format:Y-m-d H:i:s']
        ];
    }
}
