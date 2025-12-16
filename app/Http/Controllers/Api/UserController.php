<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $status = $request->query('status');
        $pagination = $request->query('pagination');

        $User = User::when($status === "inactive", function ($query) {
            $query->onlyTrashed();
        })
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        if (!$pagination) {
            UserResource::collection($User);
        } else {
            $User = UserResource::collection($User);
        }
        return $this->responseSuccess('User display successfully', $User);
    }

    public function store(UserRequest $request)
    {
        $create_user = User::create([
            "name" => $request["name"],
            "role_type" => $request["role_type"],
            "username" => $request["username"],
            "password" => $request["username"],
        ]);

        return $this->responseCreated('User Successfully Created', $create_user);
    }

    public function update(UserRequest $request, $id)
    {
        $userID = User::find($id);

        if (!$userID) {
            return $this->responseUnprocessable('Invalid ID provided for updating. Please check the ID and try again.', '');
        }

        if (CashAdvance::where('request_by_id', $id)->where('status', 'For Approval')->exists()) {
            return $this->responseUnprocessable('Unable to update. This user has associated cash advance records.', '');
        }

        $userID->mobile_number = $request["personal_info"]["mobile_number"];
        $userID->one_charging_sync_id = $request["personal_info"]["one_charging_sync_id"];
        $userID->username = $request['username'];
        $userID->pcf_branch_id = $request['pcf_branch_id'];
        $userID->role_id = $request['role_id'];

        if (!$userID->isDirty()) {
            return $this->responseSuccess('No Changes', $userID);
        }

        $userID->save();

        return $this->responseSuccess('Users successfully updated', $userID);
    }

    public function archived(Request $request, $id)
    {
        if ($id == auth('sanctum')->user()->id) {
            return $this->responseUnprocessable('', 'Unable to archive. You cannot archive your own account.');
        }

        $user = User::withTrashed()->find($id);

        if (!$user) {
            return $this->responseUnprocessable('', 'Invalid id please check the id and try again.');
        }

        if ($user->deleted_at) {

            $user->restore();
            return $this->responseSuccess('user successfully restore', $user);
        }

        if (!$user->deleted_at) {

            $user->delete();
            return $this->responseSuccess('user successfully archive', $user);
        }
    }
}
