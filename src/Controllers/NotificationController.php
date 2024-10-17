<?php

namespace Src\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Src\Models\Notification;
use Src\Models\NotifToken;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Notification::query()->where('user', $user->id)->orderBy('created_at', 'desc');
        $role = $request->input('role') ?? 'all';
        if ($role != 'all') {
            $query->where('position', $role);
        }
        $totalRecords = $query->count();
        $limit = (int)$request->input('limit', 10);
        $currentPage = (int)$request->input('page', 1);
        $offset = ($currentPage - 1) * $limit;
        $query->offset($offset)->limit($limit);

        $results = $query->get();
        if (empty($results)) {
            return response()->json(['code' => 404, 'data' => []]);
        }

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
    public function unreadCount()
    {
        $user = auth()->user();
        $query = Notification::where('readed_at', NULL)->where('user', $user->id);
        $totalRecords = $query->count();
        $countsByPosition = $query->selectRaw('position, COUNT(*) as count')->groupBy('position')->get();
        $positionCounts = [];

        foreach ($countsByPosition as $count) {
            $positionCounts[$count->position] = $count->count;
        }
        return response()->json(['code' => 200, 'data' => [
            'all' => $totalRecords,
            'marketing' => $positionCounts['marketing'] ?? 0,
            'finance' => $positionCounts['finance'] ?? 0,
            'delivery' => $positionCounts['delivery'] ?? 0,
        ]]);
    }
    public function setRead($id, Request $request)
    {
        $now = Carbon::now();
        $term = $id;
        if ($term === 'all') {
            Notification::where('readed_at', NULL)->update(['readed_at' => $now]);
        } else {
            Notification::find($term)->update(['readed_at' => $now]);
        }
        return response()->json(['code' => 200]);
    }
    public function setToken (Request $request)
    {
        $user = auth()->user();
        if(NotifToken::where('user_id', $user->id)->where('token', $request->input('token'))->count() < 1) {
            $otherToken =NotifToken::where('token', $request->input('token'))->where('user_id', '<>', $user->id)->first();
            if($otherToken) {
                $otherToken->delete();
            }
            $table = NotifToken::where('user_id', $user->id);
            $count =  $table->count();
            if($count >= 5) {
                $table->first()->delete();
            }
            NotifToken::create([
                'user_id' => $user->id,
                'token' => $request->input('token')
            ]);
        }
        return response()->json(['code' => 200]);
    }
}
