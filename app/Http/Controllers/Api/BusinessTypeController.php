<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessTypeRequest;
use App\Models\BusinessType;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class BusinessTypeController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $BusinessType = BusinessType::when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseSuccess('Business Type display successfully', $BusinessType);
    }

    public function store(BusinessTypeRequest $request)
    {
        $create_business_type = BusinessType::create([
            "name" => $request->name,
        ]);

        return $this->responseCreated('Business Type Successfully Created', $create_business_type);
    }

    public function update(BusinessTypeRequest $request, $id)
    {
        $business_type = BusinessType::find($id);

        if (!$business_type) {
            return $this->responseNotFound('', 'Invalid ID provided for updating. Please check the ID and try again.');
        }

        $business_type->name = $request['name'];

        if (!$business_type->isDirty()) {
            return $this->responseSuccess('No Changes', $business_type);
        }
        $business_type->save();

        return $this->responseSuccess('Business Type successfully updated', $business_type);
    }


    public function archived(Request $request, $id)
    {
        $business_type = BusinessType::withTrashed()->find($id);

        if (!$business_type) {
            return $this->responseUnprocessable('', 'Invalid id please check the id and try again.');
        }

        if ($business_type->deleted_at) {

            $business_type->restore();

            return $this->responseSuccess('Business Type successfully restore', $business_type);
        }

        if (!$business_type->deleted_at) {

            $business_type->delete();

            return $this->responseSuccess('Business Type successfully archive', $business_type);
        }
    }
}
