<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalCustomerRequest extends FormRequest
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
            'name' => 'required|string|unique:external_customers,name,' . $this->route('external_customer'),
            'customer_id' => [
                "sometimes",
                'required',
                'integer',
                'exists:external_customers,id',
            ],
            'vouchers' => 'required|array|min:1',
            'vouchers.*.business_type_id' => [
                'required',
                'integer',
                'exists:business_types,id',
                'distinct'
            ],
            'vouchers.*.business_type_id' => [
                'required',
                'integer',
                'exists:business_types,id',
                'distinct'
            ],
            'vouchers.*.amount' => 'required|integer|min:1',

        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required',
            'name.unique' => 'This Name is already registered',

            'vouchers.*.business_type_id.required' => 'Business type is required for each voucher',
            'vouchers.*.business_type_id.exists' => 'Invalid business type selected',
            'vouchers.*.business_type_id.distinct' => 'Duplicate business types are not allowed',
            'vouchers.*.amount.required' => 'Amount is required for each voucher',
            'vouchers.*.amount.integer' => 'Amount must be a whole number',
            'vouchers.*.amount.min' => 'Amount must be at least 1',
        ];
    }
}
