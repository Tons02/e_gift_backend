<?php

namespace App\Filters;

use App\Models\ExternalCustomer;
use App\Models\InternalCustomer;
use Essa\APIToolKit\Filters\QueryFilters;

class VoucherFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    public function search_by_id_no($search_by_id_no)
    {
        if (!empty($search_by_id_no)) {
            $this->builder->whereHasMorph(
                'voucherable',
                [InternalCustomer::class],
                fn($q) => $q->where('id_no', $search_by_id_no)
            );
        }

        return $this;
    }

    public function name($name)
    {
        if (!empty($name)) {
            $this->builder->whereHasMorph(
                'voucherable',
                [ExternalCustomer::class],
                fn($q) => $q->where('name', $name)
            );
        }

        return $this;
    }

    public function business_type_id($business_type_id)
    {
        if (!$business_type_id) {
            return $this;
        }

        $this->builder->where('business_type_id', $business_type_id);

        return $this;
    }

    public function filter_status($filter_status)
    {
        if (!$filter_status) {
            return $this;
        }

        $this->builder->where('status', $filter_status);

        return $this;
    }
}
