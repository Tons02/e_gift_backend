<?php

namespace App\Filters;

use App\Models\InternalCustomer;
use Essa\APIToolKit\Filters\QueryFilters;

class VoucherFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];

    public function search_by_id_no($search_by_id_no)
    {
        if (!empty($search_by_id_no)) {
            $this->builder->whereHasMorph(
                'voucherable',
                [InternalCustomer::class],
        fn ($q) => $q->where('id_no', $search_by_id_no)
            );
        }

        return $this;
    }

}
