<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');

        $Employee = Employee::when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseSuccess('Employee display successfully', $Employee);
    }

    public function store(EmployeeRequest $request)
    {
        $create_employee = Employee::create([
            "id_no" => $request->id_no,
            "first_name" => $request->first_name,
            "middle_name" => $request->middle_name,
            "last_name" => $request->last_name,
            "suffix" => $request->suffix,
            "birth_date" => $request->birth_date,
        ]);

        return $this->responseCreated('Employee Successfully Created', $create_employee);
    }

    public function update(EmployeeRequest $request, $id)
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->responseNotFound('', 'Invalid ID provided for updating. Please check the ID and try again.');
        }

        $employee->id_no = $request['id_no'];
        $employee->first_name = $request['first_name'];
        $employee->middle_name = $request['middle_name'];
        $employee->last_name = $request['last_name'];
        $employee->suffix = $request['suffix'];
        $employee->birth_date = $request['birth_date'];

        if (!$employee->isDirty()) {
            return $this->responseSuccess('No Changes', $employee);
        }
        $employee->save();

        return $this->responseSuccess('Employee successfully updated', $employee);
    }


    public function archived(Request $request, $id)
    {
        $employee = Employee::withTrashed()->find($id);

        if (!$employee) {
            return $this->responseUnprocessable('', 'Invalid id please check the id and try again.');
        }

        if ($employee->deleted_at) {

            $employee->restore();

            return $this->responseSuccess('Employee successfully restore', $employee);
        }

        if (!$employee->deleted_at) {

            $employee->delete();

            return $this->responseSuccess('Employee successfully archived', $employee);
        }
    }
}
