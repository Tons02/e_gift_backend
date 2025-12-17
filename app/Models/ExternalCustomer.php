<?php

namespace App\Models;

use App\Filters\ExternalFilter;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalCustomer extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        "updated_at",
    ];

    protected string $default_filters = ExternalFilter::class;

    public function vouchers(): MorphMany
    {
        return $this->morphMany(Voucher::class, 'customer');
    }

    /**
     * Check if customer has any vouchers that are not available
     */
    public function hasNonAvailableVouchers()
    {
        return $this->vouchers()->where('status', '!=', 'Available')->exists();
    }

    /**
     * Get all vouchers that are not available
     */
    public function getNonAvailableVouchers()
    {
        return $this->vouchers()
            ->where('status', '!=', 'Available')
            ->get(['id', 'reference_number', 'status', 'business_type_id', 'amount']);
    }
}
