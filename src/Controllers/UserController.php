<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\User;


class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', '%' . $search . '%');
            $query->orWhere('email', 'like', '%' . $search . '%');
        }
        $query->orderBy($request->input('order_by', 'id'), $request->input('sort', 'asc'));

        $totalRecords = $query->count();
        $limit = (int) $request->input('limit', 10);
        $currentPage = (int) $request->input('page', 1);
        $offset = ($currentPage - 1) * $limit;

        $query->offset($offset)->limit($limit);

        $results = $query->get();

        return response()->json([
            'code' => 200,
            'data' => $results,
            'meta' => [
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $limit),
                'current_page' => $currentPage,
                'limit_per_page' => $limit,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|max:100|unique:users',
            'password' => 'required|string|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{6,}$/',
            'position' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->get('password')),
                'position' => $request->get('position'),
            ]);
            return response()->json(['code' => 200, 'data' => $user]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'data' => $th]);
        }
    }
    public function destroy($id)
    {
        if ($id !== 1) {
            try {
                $item = User::findOrFail($id);
                $item->delete();
                return response()->json(['code' => 200]);
            } catch (ModelNotFoundException $e) {
                return response()->json(['code' => 404, 'message' => 'Item Not Found!']);
            }
        } else {
            return response()->json(['code' => 404, 'message' => 'Item Not Found!']);
        }
    }
    public function show($id)
    {
        try {
            $item = User::findOrFail($id);
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'message' => 'Item Not Found!']);
        }
    }

    public function update($id, Request $request)
    {

        $rules = [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|max:100',
            'password' => 'nullable|string|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{6,}$/',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $item = User::findOrFail($id);
            if (!empty($request->get('password'))) {
                $item->update([
                    'name' => $request->get('name'),
                    'email' => $request->get('email'),
                    'password' => Hash::make($request->get('password')),
                    'position' => $request->get('position'),
                ]);
            } else {
                $item->update([
                    'name' => $request->get('name'),
                    'email' => $request->get('email'),
                    'position' => $request->get('position'),
                ]);
            }
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'message' => 'Item Not Found!']);
        }
    }
}
