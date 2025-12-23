<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class InternalFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [
        'id_no',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
    ];
}
