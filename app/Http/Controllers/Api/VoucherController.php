<?php

namespace App\Http\Controllers\Api;

use App\Exports\VoucherExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVoucherSearchResource;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VoucherController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');
        $pagination = $request->query('pagination');
        $claimed_date_start = $request->query(key: 'claimed_date_start');
        $claimed_date_end = $request->query('claimed_date_end');


        $Voucher = Voucher::with('voucherable', 'business_type')
            ->when($status === "inactive", function ($query) {
                $query->onlyTrashed();
            })
            ->when($status === "inactive", function ($query) { // claimed_date_start claimed_date_end use this when present
                $query->onlyTrashed();
            })
            // claimed date range
            ->when($claimed_date_start, function ($query) use ($claimed_date_start, $claimed_date_end) {
                $start = Carbon::parse($claimed_date_start)->startOfDay();
                $end = $claimed_date_end
                    ? Carbon::parse($claimed_date_end)->endOfDay()
                    : Carbon::now()->endOfDay();

                $query->whereBetween('claimed_date', [$start, $end]);
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
        return $this->responseSuccess('Public Voucher Search display successfully', $Voucher);
    }

    public function public_external_employee_voucher_search(Request $request)
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
        return $this->responseSuccess('Public Voucher Search display successfully', $Voucher);
    }

    public function cashier_voucher_search(Request $request)
    {
        $status = $request->query('status');
        $pagination = $request->query('pagination');

        $query = Voucher::with('voucherable', 'business_type')
            ->when($status === "inactive", function ($query) {
                $query->onlyTrashed();
            })
            ->orderBy('created_at', 'desc')
            ->useFilters();

        // ✅ If pagination is disabled → return SINGLE OBJECT
        if (!$pagination) {
            $voucher = $query->first(); // or firstOrFail()

            if (!$voucher) {
                return $this->responseUnprocessable('', 'No voucher found');
            }

            return $this->responseSuccess(
                'Cashier Single External Customer Search display successfully',
                new PublicVoucherSearchResource($voucher)
            );
        }

        // ✅ If pagination is enabled → return COLLECTION
        $vouchers = $query->dynamicPaginate();

        return $this->responseSuccess(
            'Cashier Single External Customer Search display successfully',
            PublicVoucherSearchResource::collection($vouchers)
        );
    }

    public function cashier_external_employee_voucher_search(Request $request)
    {
        $status = $request->query('status');
        $pagination = $request->query('pagination');

        $query = Voucher::with('voucherable', 'business_type')
            ->when($status === "inactive", function ($query) {
                $query->onlyTrashed();
            })
            ->orderBy('created_at', 'desc')
            ->useFilters();

        // ✅ If pagination is disabled → return SINGLE OBJECT
        if (!$pagination) {
            $voucher = $query->first(); // or firstOrFail()

            if (!$voucher) {
                return $this->responseUnprocessable('', 'No voucher found');
            }

            return $this->responseSuccess(
                'Cashier Single External Customer Search display successfully',
                new PublicVoucherSearchResource($voucher)
            );
        }

        // ✅ If pagination is enabled → return COLLECTION
        $vouchers = $query->dynamicPaginate();

        return $this->responseSuccess(
            'Cashier Single External Customer Search display successfully',
            PublicVoucherSearchResource::collection($vouchers)
        );
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

        return $this->responseSuccess('Voucher claimed successfully',  new PublicVoucherSearchResource($voucher));
    }

    public function export_voucher(Request $request)
    {
        $query = Voucher::with([
            'business_type',
            'voucherable',
            'redeemed_by_user'
        ]);

        // Apply claimed date range filter
        if ($request->has('claimed_date_start') && $request->claimed_date_start) {
            $query->where('claimed_date', '>=', $request->claimed_date_start);
        }

        if ($request->has('claimed_date_end') && $request->claimed_date_end) {
            $query->where('claimed_date', '<=', $request->claimed_date_end);
        }

        // Apply filter_status (voucher status like Available, Redeemed, etc.)
        if ($request->has('filter_status') && $request->filter_status) {
            $query->where('status', $request->filter_status);
        }

        if ($request->has('business_type_id') && $request->business_type_id) {
            $query->where('business_type_id', $request->business_type_id);
        }

        $vouchers = $query->get();

        // Use claimed_date_end as target date, or default to now
        $targetDate = $request->input('claimed_date_end', now());

        // Generate filename with timestamp
        $filename = 'vouchers_export_' . Carbon::now()->format('Y_m_d_His') . '.xlsx';

        // Export to Excel
        return Excel::download(
            new VoucherExport($vouchers, $targetDate),
            $filename
        );
    }

    public function import_internal_employee(Request $request) {}
}
