<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InternalCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $internalCustomerId = $this->route('id') ?? $this->route('internal_customer');
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'id_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('internal_customers', 'id_no')
                    ->ignore($internalCustomerId)
                    ->whereNull('deleted_at')
            ],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'required|date|before:today',
            'one_charging_sync_id' => [
                'required',
                'integer',
                'exists:one_chargings,sync_id',
                'distinct'
            ],
            'customer_id' => [
                "sometimes",
                'required',
                'integer',
                'exists:internal_customers,id',
            ],
            'vouchers' => 'required|array|min:1',
            'vouchers.*.voucher_id' => [
                'nullable',
                'integer',
                Rule::exists('vouchers', 'id')->where(function ($query) use ($internalCustomerId) {
                    if ($internalCustomerId) {
                        $query->where('customer_id', $internalCustomerId)
                            ->where('customer_type', 'App\Models\InternalCustomer');
                    }
                })
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
            'id_no.required' => 'ID number is required',
            'id_no.unique' => 'This ID number is already registered',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'birth_date.required' => 'Birth date is required',
            'birth_date.before' => 'Birth date must be before today',

            'vouchers.required' => 'At least one voucher is required',
            'vouchers.min' => 'At least one voucher is required',
            'vouchers.*.voucher_id.exists' => 'Invalid voucher ID or voucher does not belong to this customer',
            'vouchers.*.business_type_id.required' => 'Business type is required for each voucher',
            'vouchers.*.business_type_id.exists' => 'Invalid business type selected',
            'vouchers.*.business_type_id.distinct' => 'Duplicate business types are not allowed',
            'vouchers.*.amount.required' => 'Amount is required for each voucher',
            'vouchers.*.amount.integer' => 'Amount must be a whole number',
            'vouchers.*.amount.min' => 'Amount must be at least 1',
        ];
    }
}
