<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OneChargingRequest;
use App\Models\OneCharging;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class OneChargingController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $OneCharging = OneCharging::when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseSuccess('One Charging display successfully', $OneCharging);
    }

    public function store(OneChargingRequest $request)
    {
        OneCharging::upsert(
            $request->one_chargings,
            ['sync_id'],
            [
                'code',
                'name',
                'created_at',
                'updated_at',
                'deleted_at'
            ]
        );

        return $this->responseSuccess('Data has been synchronized successfully.');
    }

    // public function sync_from_one_rdf(Request $request)
    // {
    //     $rawData = $request->all();

    //     $sync = collect($request->all())->map(function ($item) {
    //         return [
    //             ...$item,
    //             'unit_id' => $item['department_unit_id'],
    //             'unit_code' => $item['department_unit_code'],
    //             'unit_name' => $item['department_unit_name'],
    //         ];
    //     })->map(function ($item) {
    //         unset($item['department_unit_id'], $item['department_unit_code'], $item['department_unit_name']);
    //         return $item;
    //     })->toArray();

    //     $charging = OneCharging::upsert(
    //         $sync,
    //         ["sync_id"],
    //         [
    //             "code",
    //             "name",
    //             "company_id",
    //             "company_code",
    //             "company_name",
    //             "business_unit_id",
    //             "business_unit_code",
    //             "business_unit_name",
    //             "department_id",
    //             "department_code",
    //             "department_name",
    //             "unit_id",
    //             "unit_code",
    //             "unit_name",
    //             "sub_unit_id",
    //             "sub_unit_code",
    //             "sub_unit_name",
    //             "location_id",
    //             "location_code",
    //             "location_name",
    //             "deleted_at",
    //         ]
    //     );

    //     return $this->responseSuccess($charging, 'Data has been synchronized successfully.');
    // }
}
