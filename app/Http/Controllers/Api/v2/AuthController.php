<?php

namespace App\Http\Controllers\Api\v1;

use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


/**
 * API Version 2 - AuthController
 */
class AuthController extends Controller
{
    /**
     * Register a User
     *
     * Provide registration capability to the client app
     *
     * Registration requires:
     * - name
     * - valid email address
     * - password (min 6 character)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        //  check https://laravel.com/docs/12.x/validation#rule-email
        $validator = Validator::make(
            $request->all(),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users',],
                'password' => ['required', 'string', 'min:6', 'confirmed',],
                'password_confirmation' => ['required', 'string', 'min:6',],
            ]
        );

        if ($validator->fails()) {
            return ApiResponse::error(
                ['error' => $validator->errors()],
                'Registration details error',
                401
            );
        }

        $user = User::create([
            'name' => $validator->validated()['name'],
            'email' => $validator->validated()['email'],
            'password' => Hash::make(
                $validator->validated()['password']
            ),
        ]);

        $token = $user->createToken('MyAppToken')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'user' => $user,
            ],
            'User successfully created',
            201
        );
    }

    /**
     * User Login
     *
     * Attempt to log the user in using email
     * and password based authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Alternative using string based validation rules
        // $validator = Validator::make($request->all(), [
        //     'email' => 'required|string|email|max:255',
        //     'password' => 'required|string|min:6',
        // ]);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255',],
            'password' => ['required', 'string',],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                [
                    'error' => $validator->errors()
                ],
                'Invalid credentials',
                401
            );
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error(
                [],
                'Invalid credentials',
                401);
        }

        $user = Auth::user();
        $token = $user->createToken('MyAppToken')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'user' => $user,
            ],
            'Login successful'
        );
    }

    /**
     * User Profile API
     *
     * Provide the user's profile information, including:
     * - name,
     * - email,
     * - email verified,
     * - created at, and
     * - updated at.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(
            [
                'user' => $request->user(),
            ],
            'User profile request successful'
        );
    }

    /**
     * User Logout
     *
     * Log user out of system, cleaning token and session details.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponse::success(
            [],
            'Logout successful'
        );
    }

}
