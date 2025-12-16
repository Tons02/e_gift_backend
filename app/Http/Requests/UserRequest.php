<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
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
            "name" => [
                "required",
                "string",
                $this->route()->user
                    ? "unique:users,name," . $this->route()->user
                    : "unique:users,name",
            ],
            "role_type" => "sometimes|required|in:admin,cashier,finance,audit",
            "username" => [
                "required",
                "string",
                $this->route()->user
                    ? "unique:users,username," . $this->route()->user
                    : "unique:users,username",
            ],
            "password" => ["sometimes", "required", "string", "min:4"],
            'business_type_id' => 'required|array',
            'business_type_id.*' => 'exists:business_types,id',
        ];
    }
}
