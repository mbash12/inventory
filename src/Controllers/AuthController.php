<?php

namespace Src\Controllers;

use Src\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use JWTAuth;



class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.verify', ['except' => ['login', 'register', 'forgotPassword', 'verifyOtp', 'resetPassword']]);
    }
    public function login(Request $request)
    {
        $rules = [
            // 'email' => 'required|string|email|max:100',
            'email' => 'required|string|max:100',
            'password' => 'required|string|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{6,}$/',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please input valid value',
                'errors' => $validator->errors()
            ]);
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json([
                'code' => 422,
                'message' => 'Invalid email or password',
            ]);
        }
        return response()->json([
            'code' => 200,
            'data' => array(
                'user' => auth()->user(),
                'token' => $token,
                // 'expires_in' => auth('api')->factory()->getTTL() * 60
            )
        ]);
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|max:100|unique:users',
            // 'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9]).{6,}$/',
            'position' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }

        $user = User::create([
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password')),
            'position' => $request->get('position'),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'code' => 200,
            'data' => array(
                'user' => $user,
                'token' => $token,
                // 'expires_in' => auth('api')->factory()->getTTL() * 60
            )
        ]);
    }

    public function getaccount()
    {
        $user = auth()->user();
        return response()->json([
            'code' => 200,
            'data' => array(
                'user' => $user,
            )
        ]);
    }


    public function logout()
    {
        auth()->logout();
        return response()->json([
            'code' => 200,
        ]);
    }
    public function refresh()
    {
        $token = $this->respondWithToken(auth()->refresh());
        return response()->response()->json([
            'code' => 200,
            'data' => array(
                'user' => auth()->user(),
                'token' => $token,
                'expires_in' => auth('api')->factory()->getTTL() * 60
            )
        ], 200);
    }
    public function forgotPassword(Request $request)
    {
        $rules = [
            'email' => 'required|string|max:100',
            // 'email' => 'required|string|email|max:100',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please input valid value',
                'errors' => $validator->errors()
            ]);
        }
        $user =  User::where(['email' => $request->email])->first();
        if (!$user) {
            return response()->json([
                'code' => 422,
                'message' => 'Please input valid value',
                'errors' => [
                    "email" => ["Invalid email address"]
                ]
            ]);
        }
        $code = mt_rand(100000, 999999);
        $user->update(['otp_code' => $code]);
        return response()->json([
            'code' => 200,
        ]);
    }
    public function verifyOtp(Request $request)
    {
        $rules = [
            'email' => 'required|string|max:100',
            // 'email' => 'required|string|email|max:100',
            'otp_code' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        $user =  User::where(['email' => $request->email, 'otp_code' => $request->otp_code])->first();
        if (!$user) {
            return response()->json([
                'code' => 422,
                'message' => 'Invalid otp code'
            ]);
        }

        return response()->json([
            'code' => 200,
        ]);
    }
    public function resetPassword(Request $request)
    {
        $rules = [
            'email' => 'required|string|max:100',
            // 'email' => 'required|string|email|max:100',
            'otp_code' => 'required',
            'password' => 'required|string|confirmed|min:6|regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{6,}$/',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => 'Please input valid value',
                'errors' => $validator->errors()
            ]);
        }
        $user =  User::where(['email' => $request->email, 'otp_code' => $request->otp_code])->first();
        if (!$user) {
            return response()->json([
                'code' => 422,
                'message' => 'Invalid OTP code'
            ]);
        }

        $user->update(['otp_code' => NULL, 'password' => Hash::make($request->get('password'))]);

        return response()->json([
            'code' => 200,
        ]);
    }
    public function updateAccount(Request $request)
    {

        $rules = [
            'name' => 'string|between:2,100',
            'email' => 'string|max:100',
            // 'email' => 'string|email|max:100',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }
        $userId = auth()->user()->id;
        $user =  User::find($userId);
        if (!$user) {
            return response()->json([
                'code' => 404,
            ]);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'code' => 200,
            'data' => array(
                'user' => $user,
            )
        ]);
    }
    public function updatePassword(Request $request)
    {

        $rules = [
            'old_password' => 'required|string|min:6',
            'password' => 'required|string|confirmed|min:6',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'errors' => $validator->errors()
            ]);
        }

        $userId = auth()->user()->id;
        $user =  User::find($userId);
        if (!$user) {
            return response()->json([
                'code' => 404,
            ]);
        }

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'code' => FALSE,
                'errors' => [
                    "old_password" => "Old password didn't match"
                ]
            ]);
        }

        $user->update(['password' => Hash::make($request->get('password'))]);

        return response()->json([
            'code' => 200,
        ]);
    }
}
