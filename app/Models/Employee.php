<?php

namespace App\Models;

use App\Filters\EmployeeFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $fillable = [
        'id_no',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date'
    ];

    protected $hidden = [
        "updated_at",
    ];

    protected string $default_filters = EmployeeFilter::class;
}
