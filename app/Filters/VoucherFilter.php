<?php

namespace App\Filters;

use App\Models\InternalCustomer;
use Essa\APIToolKit\Filters\QueryFilters;

class VoucherFilter extends QueryFilters
{
    protected array $allowedFilters = [];

    protected array $columnSearch = [];

    public function id_no($id_no)
    {
        if ($id_no) {
            $this->builder->whereHasMorph(
                'voucherable',
                [InternalCustomer::class],
                function ($query) use ($id_no) {
                    $query->where('id_no', $id_no);
                }
            );
        }

        return $this;
    }
}
