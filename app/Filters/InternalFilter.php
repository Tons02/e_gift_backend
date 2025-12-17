<?php

namespace App\Filters;

use Essa\APIToolKit\Filters\QueryFilters;

class InternalFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];
}
