<?php

namespace App\Models;

use App\Filters\BusinessTypeFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessType extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $fillable = [
        'name',
    ];

    protected string $default_filters = BusinessTypeFilter::class;

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'users_business_types'
        );
    }
}
