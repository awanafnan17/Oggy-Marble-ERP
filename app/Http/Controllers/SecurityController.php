<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SecurityController extends Controller
{
    /**
     * Show self password change form.
     */
    public function showPasswordForm()
    {
        return Inertia::render('Security/MyPassword');
    }

    /**
     * Update currently authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided current password does not match our records.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('success', 'Your password has been changed successfully.');
    }

    /**
     * Gate admin/owner endpoints.
     */
    protected function authorizeAdminOwner()
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('owner'))) {
            abort(403, 'Unauthorized action. Admin or Owner role required.');
        }
    }

    /**
     * View list of users.
     */
    public function indexUsers()
    {
        $this->authorizeAdminOwner();

        $users = User::with('roles')->orderBy('name')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status,
                'roles' => $u->roles->pluck('display_name')->toArray(),
            ];
        });

        return Inertia::render('Security/UserManagement', [
            'users' => $users,
        ]);
    }

    /**
     * Reset user password (assign temporary password).
     */
    public function resetUserPassword(Request $request, $id)
    {
        $this->authorizeAdminOwner();

        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = User::findOrFail($id);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', "Password for user {$user->name} reset successfully.");
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleUserStatus(Request $request, $id)
    {
        $this->authorizeAdminOwner();

        $user = User::findOrFail($id);

        // 1. Self-disable prevention check
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'status' => 'Lockout prevention: You cannot disable your own active account.',
            ]);
        }

        // 2. Last active admin/owner prevention check
        $activeAdminOwnerCount = User::where('status', 'active')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'owner']);
            })->count();

        if ($user->hasRole(['admin', 'owner']) && $activeAdminOwnerCount <= 1 && $user->status === 'active') {
            throw ValidationException::withMessages([
                'status' => 'Security block: You cannot disable the last active administrator or owner account.',
            ]);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        return back()->with('success', "User status updated to {$newStatus}.");
    }
}
