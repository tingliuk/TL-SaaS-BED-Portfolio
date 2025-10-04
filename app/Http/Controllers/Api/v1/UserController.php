<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * UserController
 * 
 * Handles all user-related API operations including profile management,
 * user administration, and role management. Implements role-based permissions
 * where users can manage their own profiles, while staff+ can manage all users.
 * 
 * @package App\Http\Controllers\Api\v1
 * @author Ting Liu
 * @version 1.0.0
 * @since 2025-10-04
 * 
 * @method JsonResponse profile() Get current user profile
 * @method JsonResponse updateProfile(UpdateUserRequest $request) Update current user profile
 * @method JsonResponse updatePassword(UpdatePasswordRequest $request) Update current user password
 * @method JsonResponse deleteProfile() Delete current user profile
 * @method JsonResponse index(Request $request) List all users (admin)
 * @method JsonResponse store(StoreUserRequest $request) Create new user (admin)
 * @method JsonResponse show(string $id) Get specific user (admin)
 * @method JsonResponse update(UpdateUserRequest $request, string $id) Update specific user (admin)
 * @method JsonResponse destroy(string $id) Delete specific user (admin)
 * @method JsonResponse assignRoles(Request $request, string $id) Assign roles to user (admin)
 * @method JsonResponse changeStatus(Request $request, string $id) Change user status (admin)
 * 
 */
class UserController extends Controller
{
    /**
     * Get current user profile.
     * 
     * Retrieves the authenticated user's profile information including
     * personal details, roles, and permissions. This endpoint is accessible
     * to all authenticated users for their own profile.
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing user profile with roles and permissions
     * 
     * @api GET /api/v1/me
     * @permission None (authenticated users only)
     */
    public function profile(): JsonResponse
    {
        $user = request()->user();
        $user->load(['roles', 'permissions']);

        return ApiResponse::success(['user' => $user], 'Profile retrieved');
    }

    /**
     * Update current user profile.
     * 
     * Updates the authenticated user's profile information including
     * personal details like name, email, and other profile fields.
     * Users can only update their own profile information.
     * 
     * @param UpdateUserRequest $request Validated request containing:
     *                                  - name (optional): User's display name
     *                                  - given_name (optional): User's given name
     *                                  - family_name (optional): User's family name
     *                                  - email (optional): User's email address
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the updated user profile
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api PUT /api/v1/me
     * @permission None (authenticated users only, own profile)
     */
    public function updateProfile(UpdateUserRequest $request): JsonResponse
    {
        $user = request()->user();
        $user->update($request->validated());
        $user->load(['roles', 'permissions']);

        return ApiResponse::success(['user' => $user], 'Profile updated');
    }

    /**
     * Update current user password.
     * 
     * Updates the authenticated user's password with proper validation
     * and security measures. Requires current password confirmation
     * and enforces strong password requirements.
     * 
     * @param UpdatePasswordRequest $request Validated request containing:
     *                                      - current_password (required): Current password for verification
     *                                      - password (required): New password (min 8 chars, confirmed)
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (password updated)
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api PUT /api/v1/me/password
     * @permission None (authenticated users only, own profile)
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = request()->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error(['errors' => ['current_password' => ['The current password is incorrect.']]], 'Current password is incorrect', 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password updated successfully. Please log in again.');
    }

    /**
     * Delete current user profile.
     * 
     * Soft deletes the authenticated user's profile, making it unavailable
     * but preserving the data for potential restoration. This action
     * revokes all user tokens and logs them out immediately.
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (user is soft deleted)
     * 
     * @api DELETE /api/v1/me
     * @permission None (authenticated users only, own profile)
     */
    public function deleteProfile(): JsonResponse
    {
        $user = request()->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete the user
        $user->delete();

        return ApiResponse::success(null, 'Profile deleted successfully');
    }

    /**
     * Display a listing of users (admin only).
     * 
     * Retrieves a paginated list of all users in the system with optional
     * search and filtering capabilities. This endpoint is restricted to
     * staff+ level users for administrative purposes.
     * 
     * @param Request $request The HTTP request containing optional query parameters:
     *                        - q (string): Search term for name or email
     *                        - status (string): Filter by user status
     *                        - role (string): Filter by user role
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing paginated users with roles
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks admin permissions
     * 
     * @api GET /api/v1/users
     * @permission users.browse (Staff level 500+)
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user can browse users (Staff level 500+)
        if (!request()->user()->hasPermissionTo('users.browse')) {
            return ApiResponse::error(null, 'Unauthorized to browse users', 403);
        }

        $query = User::with(['roles']);

        // Apply search filter
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('given_name', 'like', "%{$search}%")
                  ->orWhere('family_name', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        // Apply role filter
        if ($role = $request->string('role')->toString()) {
            $query->role($role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return ApiResponse::success(['users' => $users], 'Users retrieved');
    }

    /**
     * Store a newly created user (staff+ only).
     * 
     * Creates a new user account with the provided information and assigns
     * specified roles. Implements detailed permission matrix:
     * - Staff: Can create clients and applicants only
     * - Admin: Can create all user types
     * - Superuser: Can create all user types
     * 
     * @param StoreUserRequest $request Validated request containing:
     *                                 - name (required): User's display name
     *                                 - given_name (optional): User's given name
     *                                 - family_name (optional): User's family name
     *                                 - email (required): User's email address
     *                                 - password (required): User's password
     *                                 - status (optional): User's status
     *                                 - roles (optional): Array of role names to assign
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the created user with roles
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user lacks create permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api POST /api/v1/users
     * @permission users.create (Staff level 500+)
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $currentUser = request()->user();

        // Check if user can create users (Staff level 500+)
        if (!$currentUser->hasPermissionTo('users.create')) {
            return ApiResponse::error(null, 'Unauthorized to create users', 403);
        }

        // Check if user can create the specified roles
        if ($request->has('roles') && !$this->canCreateUserWithRoles($currentUser, $request->roles)) {
            return ApiResponse::error(null, 'Unauthorized to create users with these roles', 403);
        }

        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // Assign roles if provided
        if ($request->has('roles')) {
            $user->assignRole($request->roles);
        }

        $user->load(['roles']);

        return ApiResponse::success(['user' => $user], 'User created', 201);
    }

    /**
     * Display the specified user (admin only).
     * 
     * Retrieves a specific user's information including roles and permissions.
     * This endpoint is restricted to staff+ level users for administrative
     * purposes and user management.
     * 
     * @param string $id The user ID to retrieve
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the user with roles and permissions
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks read permissions
     * 
     * @api GET /api/v1/users/{id}
     * @permission users.read (Staff level 500+)
     */
    public function show(string $id): JsonResponse
    {
        // Check if user can read users (Staff level 500+)
        if (!request()->user()->hasPermissionTo('users.read')) {
            return ApiResponse::error(null, 'Unauthorized to read users', 403);
        }

        $user = User::with(['roles', 'permissions'])->find($id);

        if (!$user) {
            return ApiResponse::error(null, "User not found", 404);
        }

        return ApiResponse::success(['user' => $user], 'User retrieved');
    }

    /**
     * Update the specified user (admin only).
     * 
     * Updates an existing user's information including personal details
     * and status. Implements detailed permission matrix:
     * - Staff: Can edit own profile, clients, and applicants only
     * - Admin: Can edit all users
     * - Superuser: Can edit all users
     * 
     * @param UpdateUserRequest $request Validated request containing:
     *                                  - name (optional): User's display name
     *                                  - given_name (optional): User's given name
     *                                  - family_name (optional): User's family name
     *                                  - email (optional): User's email address
     *                                  - status (optional): User's status
     * @param string $id The user ID to update
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the updated user with roles
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks update permissions
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * @api PUT /api/v1/users/{id}
     * @permission users.update (Staff level 500+)
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $currentUser = request()->user();
        $targetUser = User::find($id);

        if (!$targetUser) {
            return ApiResponse::error(null, "User not found", 404);
        }

        // Check permission based on detailed matrix
        if (!$this->canUpdateUser($currentUser, $targetUser)) {
            return ApiResponse::error(null, 'Unauthorized to update this user', 403);
        }

        $targetUser->update($request->validated());
        $targetUser->load(['roles']);

        return ApiResponse::success(['user' => $targetUser], 'User updated');
    }

    /**
     * Remove the specified user (admin only).
     * 
     * Soft deletes a user account, making it unavailable but preserving
     * the data for potential restoration. Implements detailed permission matrix:
     * - Staff: Can delete clients and applicants only
     * - Admin: Can delete clients, applicants, and staff only
     * - Superuser: Can delete any user except themselves
     * - No user can delete themselves
     * 
     * @param string $id The user ID to delete
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: null (user is soft deleted)
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks delete permissions
     * 
     * @api DELETE /api/v1/users/{id}
     * @permission users.delete (Staff level 500+)
     */
    public function destroy(string $id): JsonResponse
    {
        $currentUser = request()->user();
        $targetUser = User::find($id);

        if (!$targetUser) {
            return ApiResponse::error(null, "User not found", 404);
        }

        // Check permission based on detailed matrix
        if (!$this->canDeleteUser($currentUser, $targetUser)) {
            return ApiResponse::error(null, 'Unauthorized to delete this user', 403);
        }

        // Revoke all user tokens
        $targetUser->tokens()->delete();

        // Soft delete the user
        $targetUser->delete();

        return ApiResponse::success(null, 'User deleted');
    }

    /**
     * Assign roles to a user (admin only).
     * 
     * Assigns or updates roles for a specific user. This endpoint is
     * restricted to admin+ level users for role management purposes.
     * 
     * @param Request $request The HTTP request containing:
     *                        - roles (required): Array of role names to assign
     * @param string $id The user ID to assign roles to
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the user with updated roles
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks role assignment permissions
     * 
     * @api PUT /api/v1/users/{id}/roles
     * @permission users.assign-roles (Admin level 750+)
     */
    public function assignRoles(Request $request, string $id): JsonResponse
    {
        // Check if user can assign roles (Admin level 750+)
        if (!request()->user()->hasPermissionTo('users.assign-roles')) {
            return ApiResponse::error(null, 'Unauthorized to assign roles', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // Sync roles (replace existing roles)
        $user->syncRoles($request->roles);
        $user->load(['roles']);

        return ApiResponse::success(['user' => $user], 'Roles assigned successfully');
    }

    /**
     * Change user status (admin only).
     * 
     * Updates a user's status (active, suspended, banned). This endpoint
     * is restricted to staff+ level users for user management purposes.
     * 
     * @param Request $request The HTTP request containing:
     *                        - status (required): New status (active, suspended, banned)
     * @param string $id The user ID to change status for
     * 
     * @return JsonResponse Standardized JSON response containing:
     *                     - success: boolean indicating operation success
     *                     - message: Human-readable status message
     *                     - data: Object containing the user with updated status
     * 
     * @throws \Illuminate\Http\Exceptions\HttpResponseException When user not found or lacks status change permissions
     * 
     * @api PUT /api/v1/users/{id}/status
     * @permission users.change-status (Staff level 500+)
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        // Check if user can change status (Staff level 500+)
        if (!request()->user()->hasPermissionTo('users.change-status')) {
            return ApiResponse::error(null, 'Unauthorized to change user status', 403);
        }

        $user = User::find($id);

        if (!$user) {
            return ApiResponse::error(null, "User not found", 404);
        }

        $request->validate([
            'status' => 'required|string|in:active,suspended,banned',
        ]);

        $user->update(['status' => $request->status]);

        // If user is suspended or banned, revoke all tokens
        if (in_array($request->status, ['suspended', 'banned'])) {
            $user->tokens()->delete();
        }

        $user->load(['roles']);

        return ApiResponse::success(['user' => $user], 'User status updated successfully');
    }

    /**
     * Check if the current user can update the target user based on permission matrix.
     * 
     * @param User $currentUser The user making the request
     * @param User $targetUser The user being updated
     * @return bool True if update is allowed, false otherwise
     */
    private function canUpdateUser(User $currentUser, User $targetUser): bool
    {
        // Superuser can edit all users
        if ($currentUser->hasRole('superuser')) {
            return true;
        }

        // Admin can edit all users
        if ($currentUser->hasRole('admin')) {
            return true;
        }

        // Staff can edit own profile, clients, and applicants (users with 'user' role)
        if ($currentUser->hasRole('staff')) {
            // Can edit own profile
            if ($currentUser->id === $targetUser->id) {
                return true;
            }
            
            // Can edit clients and applicants (users with 'user' role)
            return $targetUser->hasRole(['client', 'user']);
        }

        // Client can only edit own profile
        if ($currentUser->hasRole('client')) {
            return $currentUser->id === $targetUser->id;
        }

        return false;
    }

    /**
     * Check if the current user can delete the target user based on permission matrix.
     * 
     * @param User $currentUser The user making the request
     * @param User $targetUser The user being deleted
     * @return bool True if deletion is allowed, false otherwise
     */
    private function canDeleteUser(User $currentUser, User $targetUser): bool
    {
        // No user can delete themselves
        if ($currentUser->id === $targetUser->id) {
            return false;
        }

        // Superuser can delete any user except themselves
        if ($currentUser->hasRole('superuser')) {
            return true;
        }

        // Admin can delete clients, applicants, and staff (but not other admins or superusers)
        if ($currentUser->hasRole('admin')) {
            return $targetUser->hasRole(['client', 'user', 'staff']);
        }

        // Staff can delete clients and applicants only
        if ($currentUser->hasRole('staff')) {
            return $targetUser->hasRole(['client', 'user']);
        }

        // Client can only delete their own profile (handled by /me endpoint)
        return false;
    }

    /**
     * Check if the current user can create users with the specified roles.
     * 
     * @param User $currentUser The user making the request
     * @param array $roles The roles to be assigned to the new user
     * @return bool True if role creation is allowed, false otherwise
     */
    private function canCreateUserWithRoles(User $currentUser, array $roles): bool
    {
        // Superuser can create users with any roles
        if ($currentUser->hasRole('superuser')) {
            return true;
        }

        // Admin can create users with any roles
        if ($currentUser->hasRole('admin')) {
            return true;
        }

        // Staff can only create clients and applicants (users with 'user' role)
        if ($currentUser->hasRole('staff')) {
            $allowedRoles = ['client', 'user'];
            foreach ($roles as $role) {
                if (!in_array($role, $allowedRoles)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
