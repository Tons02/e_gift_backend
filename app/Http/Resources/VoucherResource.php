<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'amount' => $this->amount,
            'business_types' => [
                'id' => $this->business_type->id,
                'name' => $this->business_type->name,
            ],
            'internal_customer' => $this->voucherable && $this->voucherable->id_no
                ? [
                    'id' => $this->voucherable->id,
                    'id_no' => $this->voucherable->id_no,
                    'first_name' => $this->voucherable->first_name,
                    'middle_name' => $this->voucherable->middle_name,
                    'last_name' => $this->voucherable->last_name,
                    'suffix' => $this->voucherable->suffix,
                    'one_charging' => $this->voucherable->one_charging
                        ? [
                            'sync_id' => $this->voucherable->one_charging->sync_id,
                            'code' => $this->voucherable->one_charging->code,
                            'name' => $this->voucherable->one_charging->name,
                            'created_at' => $this->voucherable->one_charging->created_at,
                        ]
                        : null,
                    'status' => $this->status,
                    'created_at' => $this->voucherable->created_at,
                ]
                : [],
            'external_customer' => $this->voucherable && $this->voucherable->name ? [
                'id' => $this->voucherable->id,
                'name' => $this->voucherable->name,
                'status' => $this->status,
                'created_at' => $this->voucherable->created_at,
            ] : [],
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
