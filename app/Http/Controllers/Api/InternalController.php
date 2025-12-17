<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InternalCustomerRequest;
use App\Models\InternalCustomer;
use App\Models\Voucher;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternalController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $InternalCustomer = InternalCustomer::with('vouchers', 'one_charging')->when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseSuccess('Internal Customer display successfully', $InternalCustomer);
    }

    public function store(InternalCustomerRequest $request)
    {
        $create_internal_customer = InternalCustomer::create([
            "one_charging_sync_id" => $request->one_charging_sync_id,
            "id_no" => $request->id_no,
            "first_name" => $request->first_name,
            "middle_name" => $request->middle_name,
            "last_name" => $request->last_name,
            "suffix" => $request->suffix,
            "birth_date" => $request->birth_date,
        ]);

        $voucher = new Voucher();
        $voucher->generate_voucher($request, $create_internal_customer);


        return $this->responseCreated('Internal Customer Successfully Created', $create_internal_customer);
    }

    public function update(InternalCustomerRequest $request, $id)
    {
        $internal_customer = InternalCustomer::findOrFail($id);

        if ($internal_customer->hasNonAvailableVouchers()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update. Some vouchers are not available for modification.',
                'data' => [
                    'non_available_vouchers' => $internal_customer->getNonAvailableVouchers()
                ]
            ], 422);
        }

        $internal_customer->update([
            "one_charging" => $request->one_charging,
            "id_no" => $request->id_no,
            "first_name" => $request->first_name,
            "middle_name" => $request->middle_name,
            "last_name" => $request->last_name,
            "suffix" => $request->suffix,
            "birth_date" => $request->birth_date,
        ]);

        try {
            if ($request->has('vouchers')) {
                $voucher = new Voucher();
                $voucher->update_or_create_vouchers($request, $internal_customer);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return $this->responseSuccess(
            'Internal Customer Successfully Updated',
            $internal_customer->fresh()->load('vouchers')
        );
    }


    public function archived(Request $request, $id)
    {
        $internal_customer = InternalCustomer::withTrashed()->find($id);

        if (!$internal_customer) {
            return $this->responseUnprocessable('', 'Invalid id please check the id and try again.');
        }

        if ($internal_customer->deleted_at) {
            // Restore the customer
            $internal_customer->restore();

            // Restore all associated vouchers
            $internal_customer->vouchers()->onlyTrashed()->restore();

            return $this->responseSuccess('Internal Customer successfully restored', $internal_customer);
        }

        if (!$internal_customer->deleted_at) {
            // Check if customer has non-available vouchers
            if ($internal_customer->hasNonAvailableVouchers()) {
                $nonAvailableVouchers = $internal_customer->getNonAvailableVouchers();
                return $this->responseFailed(
                    'Cannot archive customer with non-available vouchers',
                    ['non_available_vouchers' => $nonAvailableVouchers],
                    422
                );
            }

            // Archive the customer
            $internal_customer->delete();

            // Soft delete all associated vouchers
            $internal_customer->vouchers()->delete();

            return $this->responseSuccess('Internal Customer successfully archived', $internal_customer);
        }
    }
}
