<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\Client;


class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function index(Request $request)
    {
        try {
            $query = Client::query();
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', '%' . $search . '%');
            }
            $query->orderBy('name');
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
        } catch (\Throwable $th) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|between:2,100',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $item = Client::create([
                'name' => $request->get('name'),
            ]);
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (\Throwable $th) {
            return response()->json(['code' => 400, 'data' => $th]);
        }
    }
    public function destroy($id)
    {
        try {
            $item = Client::findOrFail($id);
            $item->delete();
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
    public function show($id)
    {
        try {
            $item = Client::findOrFail($id);
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }

    public function update($id, Request $request)
    {

        $rules = [
            'name' => 'required|string|between:2,100',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        try {
            $item = Client::findOrFail($id);
            $item->update([
                'name' => $request->get('name'),
            ]);
            return response()->json(['code' => 200, 'data' => $item]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['code' => 404, 'data' => []]);
        }
    }
}
