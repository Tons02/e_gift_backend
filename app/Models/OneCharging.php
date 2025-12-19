<?php

namespace App\Models;

use App\Filters\OneChargingFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OneCharging extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $fillable = [
        'sync_id',
        'code',
        'name',
        'company_id',
        'company_code',
        'company_name',
        'business_unit_id',
        'business_unit_code',
        'business_unit_name',
        'department_id',
        'department_code',
        'department_name',
        'unit_id',
        'unit_code',
        'unit_name',
        'sub_unit_id',
        'sub_unit_code',
        'sub_unit_name',
        'location_id',
        'location_code',
        'location_name',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected string $default_filters = OneChargingFilter::class;
}
