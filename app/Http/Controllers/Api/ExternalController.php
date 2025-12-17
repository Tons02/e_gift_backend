<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalCustomerRequest;
use App\Models\ExternalCustomer;
use App\Models\Voucher;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class ExternalController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $ExternalCustomer = ExternalCustomer::with('vouchers')->when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseSuccess('External Customer display successfully', $ExternalCustomer);
    }

    public function store(ExternalCustomerRequest $request)
    {
        $create_external_customer = ExternalCustomer::create([
            "name" => $request->name,
        ]);

        $voucher = new Voucher();
        $voucher->generate_voucher($request, $create_external_customer);


        return $this->responseCreated('External Customer Successfully Created', $create_external_customer);
    }

    public function update(ExternalCustomerRequest $request, $id)
    {
        $external_customer = ExternalCustomer::findOrFail($id);

        // Check if customer has non-available vouchers
        if ($external_customer->hasNonAvailableVouchers()) {
            $nonAvailableVouchers = $external_customer->getNonAvailableVouchers();
            return response()->json([
                'message' => 'Cannot update customer with non-available vouchers',
                'non_available_vouchers' => $nonAvailableVouchers,
            ], 422);
        }

        // Update customer details
        $external_customer->update([
            'name' => $request->name,
        ]);

        // Update or create vouchers
        if ($request->has('vouchers') && is_array($request->vouchers)) {
            $voucher = new Voucher();
            $voucher->update_or_create_vouchers($request, $external_customer);
        }

        // Load fresh data
        $external_customer->load('vouchers');

        return $this->responseSuccess('External Customer Successfully Updated', $external_customer);
    }

    public function show($id)
    {
        $external_customer = ExternalCustomer::with(['vouchers.business_type'])
            ->findOrFail($id);

        return $this->responseSuccess('External Customer retrieved successfully', $external_customer);
    }
}
