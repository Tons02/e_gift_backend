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
    ];

    protected string $default_filters = OneChargingFilter::class;
}
