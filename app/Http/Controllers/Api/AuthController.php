<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Auth; 
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter; // ← تمت إضافته
use Illuminate\Support\Str;                 // ← تمت إضافته

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|regex:/^[^<>{}]*$/|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|regex:/^[^<>{}]*$/|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'user',
            'status' => '1',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken
            ],
            'message' => 'User registered successfully'
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|regex:/^[^<>{}]*$/|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // ==========================================
        // 👇 بداية إضافة نظام الحماية (Throttle) 👇
        // ==========================================
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        // إذا تجاوز 5 محاولات، أرجع كود 429 مع عدد الثواني المتبقية
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'seconds_until_available' => $seconds,
            ], 429)->header('Retry-After', $seconds);
        }
        // ==========================================
        // 👆 نهاية إضافة نظام الحماية (Throttle) 👆
        // ==========================================

        if (!Auth::attempt($request->only('email', 'password'))) {
            
            // 👇 سجل المحاولة الفاشلة (مدة القفل 60 ثانية) 👇
            RateLimiter::hit($throttleKey, 60); 

            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        // 👇 إذا نجح تسجيل الدخول، امسح عداد المحاولات 👇
        RateLimiter::clear($throttleKey);

        $user = User::where('email', $request->email)->firstOrFail();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'token_type' => 'Bearer',
            ],
            'message' => 'User logged in successfully'
        ], 200);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully'
        ], 200);
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ], 200);
    }
}