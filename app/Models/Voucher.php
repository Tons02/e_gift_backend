<?php

namespace App\Models;

use App\Filters\VoucherFilter;
use Carbon\Carbon;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Voucher extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected $hidden = [
        "updated_at",
    ];

    protected string $default_filters = VoucherFilter::class;

    public function voucherable(): MorphTo
    {
        return $this->morphTo('voucherable', 'customer_type', 'customer_id');
    }

    public function generate_voucher($request, $customer_type)
    {
        foreach ($request->vouchers as $voucher) {
            Voucher::create([
                'business_type_id' => $voucher['business_type_id'],
                'reference_number' => Carbon::now()->format('Ymd') . Str::upper(Str::random(8)),
                'amount' => $voucher['amount'],
                'customer_id' => $customer_type->id,
                'customer_type' => get_class($customer_type),
            ]);
        }
    }

    public function update_or_create_vouchers($request, $customer_type)
    {
        $vouchersData = $request->vouchers ?? $request->voucher ?? [];

        // Collect the voucher IDs that are in the request
        $voucherIdsInRequest = collect($vouchersData)
            ->pluck('voucher_id')
            ->filter() // Remove null values
            ->toArray();

        // Track newly created voucher IDs
        $newlyCreatedIds = [];

        // Process each voucher in the request (create or update)
        foreach ($vouchersData as $voucherData) {
            if (isset($voucherData['voucher_id']) && $voucherData['voucher_id'] !== null) {
                // Update existing voucher
                $voucher = Voucher::where('id', $voucherData['voucher_id'])
                    ->where('customer_id', $customer_type->id)
                    ->where('customer_type', get_class($customer_type))
                    ->where('status', 'Available')
                    ->first();

                if ($voucher) {
                    $voucher->update([
                        'business_type_id' => $voucherData['business_type_id'],
                        'amount' => $voucherData['amount'],
                    ]);
                } else {
                    throw new \Exception('Voucher ID ' . $voucherData['voucher_id'] . ' is not available for update.');
                }
            } else {
                // Create new voucher
                $newVoucher = Voucher::create([
                    'business_type_id' => $voucherData['business_type_id'],
                    'reference_number' => Carbon::now()->format('Ymd') . Str::upper(Str::random(8)),
                    'amount' => $voucherData['amount'],
                    'customer_id' => $customer_type->id,
                    'customer_type' => get_class($customer_type),
                    'status' => 'Available',
                ]);

                // Track the newly created voucher ID
                $newlyCreatedIds[] = $newVoucher->id;
            }
        }

        // Merge existing and newly created IDs to keep
        $allVoucherIdsToKeep = array_merge($voucherIdsInRequest, $newlyCreatedIds);

        // Delete vouchers that are NOT in the request and were NOT just created
        Voucher::where('customer_id', $customer_type->id)
            ->where('customer_type', get_class($customer_type))
            ->where('status', 'Available')
            ->when(!empty($allVoucherIdsToKeep), function ($query) use ($allVoucherIdsToKeep) {
                $query->whereNotIn('id', $allVoucherIdsToKeep);
            })
            ->forceDelete();
    }

    public function business_type()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id', 'id')->withTrashed();
    }
}
