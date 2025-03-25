<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Src\Controllers\AuthController;
use Src\Controllers\UserController;
use Src\Controllers\WarehouseController;
use Src\Controllers\ShippingVendorController;
use Src\Controllers\NotificationController;
use Src\Controllers\ProjectController;
use Src\Controllers\DeliveryController;
use Src\Controllers\InitController;
use Src\Controllers\ClientController;
use Src\Controllers\InvoiceProgressController;
use Src\Controllers\ThreadsController;
use Src\Controllers\FollowupController;
use Src\Controllers\PoDepositController;
use Src\Controllers\ReportController;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


Route::post('init', [InitController::class, 'index']);
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    // requires auth
    Route::get('/account', [AuthController::class, 'getaccount']);
    Route::post('/update', [AuthController::class, 'updateAccount']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
});

Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/set-read/{id}', [NotificationController::class, 'setRead']);
    Route::post('/set-token', [NotificationController::class, 'setToken']);
});

Route::prefix('deliveries')->group(function () {
    Route::get('/logss/{id}', [DeliveryController::class, 'logss']);
    Route::get('/logs/{id}', [DeliveryController::class, 'logs']);
    Route::get('/log/{id}', [DeliveryController::class, 'log']);
    Route::get('/list/{id}', [DeliveryController::class, 'index']);
    Route::get('/misc/{id}', [DeliveryController::class, 'misc']);
    Route::get('/{id}', [DeliveryController::class, 'show']);
    Route::post('/', [DeliveryController::class, 'store']);
    Route::put('/{id}', [DeliveryController::class, 'update']);
    Route::delete('/{id}', [DeliveryController::class, 'destroy']);
});
Route::get('inventoriess/{id}', [DeliveryController::class, 'inventories']);
Route::get('inventories', [DeliveryController::class, 'inventory']);
Route::apiResource('clients', ClientController::class);

Route::apiResource('users', UserController::class);
Route::apiResource('warehouses', WarehouseController::class);
Route::apiResource('shipping-vendors', ShippingVendorController::class);
Route::get('projects/snippet/{id}', [ProjectController::class, 'snippet']);
Route::get('projects/todo', [ProjectController::class, 'todo']);
Route::put('projects/delivery/{id}', [ProjectController::class, 'delivery']);
Route::apiResource('projects', ProjectController::class);
Route::get('po-deposits/migrate', [PoDepositController::class, 'migrate']);
Route::apiResource('po-deposits', PoDepositController::class);
Route::get('report', [ReportController::class, 'index']);
Route::post('import', [ReportController::class, 'import']);

// ===========================



Route::prefix('invoice-progress')->group(function () {
    Route::get('/{id}', [InvoiceProgressController::class, 'get']);
    Route::post('/{id}', [InvoiceProgressController::class, 'store']);
});

Route::apiResource('threads', ThreadsController::class);
// Route::prefix('threads')->group(function () {
//     Route::get('/', [ThreadsController::class, 'get']);
//     Route::get('/{id}', [ThreadsController::class, 'get']);
//     Route::post('/{id}', [ThreadsController::class, 'store']);
// });


Route::prefix('marketing-followup')->group(function () {
    Route::get('/{id}', [FollowupController::class, 'get']);
    Route::post('/{id}', [FollowupController::class, 'store']);
    Route::put('/{id}', [FollowupController::class, 'update']);
});

Route::post('/upload', function (Request $request) {
    $request->validate([
        'file' => 'required|file|max:4096|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,ppt,pptx', // 4MB Max, limited file types
    ]);
    if ($request->file('file')->isValid()) {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $randomString = Str::random(10); // Generate 10 character random string
        $newFileName = $randomString . '_' . $originalName;
        
        $path = $request->file('file')->storeAs('uploads', $newFileName, 'public');
        return response()->json(['path' => $path], 201);
    }

    return response()->json(['error' => 'File upload failed'], 400);
});


Route::get('/file/{filename}', function ($filename) {
    $path = 'uploads/' . $filename;

    if (!Storage::disk('src')->exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    $file = Storage::disk('src')->get($path);
    
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $mimeType = getMimeType($extension);

    return response($file, 200)->header('Content-Type', $mimeType);
});

// Helper function to get MIME type
function getMimeType($extension) {
    $mimeTypes = [
        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        
        // documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}



Route::get('/check-db-connection', function () {
    try {
        DB::connection()->getPdo();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully connected to the database: ' . DB::connection()->getDatabaseName(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Database connection failed: ' . $e->getMessage(),
        ], 500);
    }
});