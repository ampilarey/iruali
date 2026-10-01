<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Users: give a user the support or finance role (or take it away). Admins only.
 */
class StaffRoleController extends Controller
{
    public const ASSIGNABLE = ['support', 'finance'];

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:none,'.implode(',', self::ASSIGNABLE)]);

        if ($user->is($request->user()) || $user->hasRole('admin')) {
            return back()->with('error', 'Admin accounts and your own account cannot be changed here.');
        }

        $before = \App\Support\StaffAccess::staffRoles($user);
        $user->roles()->detach(Role::whereIn('name', self::ASSIGNABLE)->pluck('id'));
        if ($data['role'] !== 'none') {
            $user->roles()->attach(Role::firstOrCreate(['name' => $data['role']], ['display_name' => ucfirst($data['role'])])->id);
        }
        $user->unsetRelation('roles');

        Audit::record('user.role', $user, ['from' => $before, 'to' => \App\Support\StaffAccess::staffRoles($user)]);

        return back()->with('success', $data['role'] === 'none' ? $user->name.' is no longer staff.' : $user->name.' is now '.$data['role'].' staff. They must turn on two-step sign-in before opening the admin area.');
    }
}
