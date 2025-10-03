<?php

namespace App\Http\Controllers\Api\v1;

use App\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


/**
 * API Version 1 - AuthController
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

    /**
     * Send password reset link to the user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::success(null, __($status));
        }

        return ApiResponse::error(['email' => __($status)], 'Unable to send reset link', 422);
    }

    /**
     * Reset the user's password using a token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens on reset
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, __($status));
        }

        return ApiResponse::error(['email' => __($status)], 'Password reset failed', 422);
    }

    /**
     * Logout all users holding the specified role.
     */
    public function logoutByRole(Request $request, string $role): JsonResponse
    {
        $users = User::role($role)->get();

        $count = 0;
        foreach ($users as $user) {
            $deleted = $user->tokens()->delete();
            $count += $deleted;
        }

        return ApiResponse::success(
            ['revoked_tokens' => $count, 'role' => $role, 'users_affected' => $users->count()],
            'Logout by role completed'
        );
    }

}
