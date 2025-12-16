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

        $User = User::with('businessTypes')->when($status === "inactive", function ($query) {
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
        $user = User::create([
            'name'      => $request->name,
            'role_type' => $request->role_type,
            'username'  => $request->username,
            'password'  => bcrypt($request->username), // ❗ hash it
        ]);

        if ($request->filled('business_type_id')) {
            $user->businessTypes()->attach($request->business_type_id);
        }

        return $this->responseCreated(
            'User Successfully Created',
            $user->load('businessTypes')
        );
    }


    public function update(UserRequest $request, $id)
    {
        $user = User::with('businessTypes')->find($id);

        if (!$user) {
            return $this->responseUnprocessable(
                'Invalid ID provided for updating. Please check the ID and try again.',
                ''
            );
        }

        // Update user fields safely
        $user->fill([
            'name'      => $request->name,
            'role_type' => $request->role_type,
            'username'  => $request->username,
        ]);

        // Optional password update
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Sync business types (pivot)
        if ($request->has('business_type_id')) {
            $user->businessTypes()->sync($request->business_type_id);
        }

        // Check if something actually changed
        if (!$user->isDirty() && !$user->businessTypes->pluck('id')->diff($request->business_type_id ?? [])->isNotEmpty()) {
            return $this->responseSuccess(
                'No Changes',
                new UserResource($user)
            );
        }

        $user->save();

        return $this->responseSuccess(
            'User successfully updated',
            new UserResource($user->load('businessTypes'))
        );
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
