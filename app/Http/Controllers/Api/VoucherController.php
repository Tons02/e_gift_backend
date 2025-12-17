<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVoucherSearchResource;
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

    public function public_voucher_search(Request $request)
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
            PublicVoucherSearchResource::collection($Voucher);
        } else {
            $Voucher = PublicVoucherSearchResource::collection($Voucher);
        }
        return $this->responseSuccess('Voucher display successfully', $Voucher);
    }

    public function claimed_voucher(Request $request, $id)
    {
      $voucher = Voucher::where('id', $id)->first();

        if (!$voucher) {
            return $this->responseUnprocessable('', 'Invalid Voucher ID.');
        }

        if ($voucher->status !== 'Available') {
            return $this->responseUnprocessable('', 'Voucher already claimed.');
        }

        if (!auth()->user()->businessTypes->contains('id', $voucher->business_type_id)) {
            return $this->responseUnprocessable(
                '',
                'You cannot claim this voucher because it is not tagged to your business type.'
            );
        }

        $voucher->update([
            "status" => "Claimed",
            "redeemed_by_user_id" => auth()->user()->id,
            "claimed_date" => Carbon::now(),
        ]);

        return $this->responseSuccess('Voucher claimed successfully', $voucher);
    }
}
