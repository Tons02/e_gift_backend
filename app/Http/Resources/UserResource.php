<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'role_type' => $this->role_type,
            'username' => $this->username,
            'created_at' => $this->created_at,
            'business_types' => BusinessTypeResource::collection(
                $this->whenLoaded('businessTypes')
            ),
            'one_charging' =>
            [
                'id' =>  $this->one_charging->id,
                'sync_id' =>  $this->one_charging->sync_id,
                'code' =>  $this->one_charging->code,
                'name' =>  $this->one_charging->name,
                'company_id' =>  $this->one_charging->company_id,
                'company_code' =>  $this->one_charging->company_code,
                'company_name' =>  $this->one_charging->company_name,
                'business_unit_id' =>  $this->one_charging->business_unit_id,
                'business_unit_code' =>  $this->one_charging->business_unit_code,
                'business_unit_name' =>  $this->one_charging->business_unit_name,
                'department_id' =>  $this->one_charging->department_id,
                'department_code' =>  $this->one_charging->department_code,
                'department_name' =>  $this->one_charging->department_name,
                'unit_id' =>  $this->one_charging->unit_id,
                'unit_code' =>  $this->one_charging->unit_code,
                'unit_name' =>  $this->one_charging->unit_name,
                'sub_unit_id' =>  $this->one_charging->sub_unit_id,
                'sub_unit_code' =>  $this->one_charging->sub_unit_code,
                'sub_unit_name' =>  $this->one_charging->sub_unit_name,
                'location_id' =>  $this->one_charging->location_id,
                'location_code' =>  $this->one_charging->location_code,
                'location_name' =>  $this->one_charging->location_name
            ],
        ];
    }
}
