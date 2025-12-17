<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');
        $pagination = $request->query('pagination');

        $Voucher = Voucher::with('voucherable', 'business_type')
            ->when($status === "inactive", function ($query) {
                $query->onlyTrashed();
            })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        if (!$pagination) {
            VoucherResource::collection($Voucher);
        } else {
            $Voucher = VoucherResource::collection($Voucher);
        }
        return $this->responseSuccess('Voucher display successfully', $Voucher);
    }

    public function show($id)
    {
        $voucher = Voucher::with('voucherable', 'business_type')->findOrFail($id);
        return $this->responseSuccess('Voucher retrieved successfully', new VoucherResource($voucher));
    }

    public function claimed_voucher(Request $request)
    {
        return $user_business_type = auth()->user()->businessTypes[0]->name;

        return  $voucher = Voucher::with('voucherable', 'business_type')->where('reference_number', $request->reference_number)
            // ->whereIn('business_type_id', $user->businessTypes->pluck('id'))
            ->where('status', 'Available')
            ->first();

        if ($voucher->status === 'Claimed') {
            return $this->responseUnprocessable('', 'Voucher already claimed.');
        }



        $voucher->update([
            "status" => "Claimed",
            "redeemed_by_user_id" => auth()->id,
            "claimed_date" => Carbon::now(),
        ]);

        return $this->responseSuccess('Voucher claimed successfully', $voucher);
    }
}
