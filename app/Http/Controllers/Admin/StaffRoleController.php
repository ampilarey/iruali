<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\ShopStaff;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Users: give a user the support, finance or catalogue role (or take it away). Admins only.
 * Shop accounts (owners and their staff) are never iruali staff: someone approving products or
 * campaign entries must not be approving their own shop's.
 */
class StaffRoleController extends Controller
{
    public const ASSIGNABLE = ['support', 'finance', 'catalogue'];

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:none,'.implode(',', self::ASSIGNABLE)]);

        if ($user->is($request->user()) || $user->hasRole('admin')) {
            return back()->with('error', 'Admin accounts and your own account cannot be changed here.');
        }
        if ($data['role'] !== 'none' && ($user->hasRole('seller') || $user->is_seller || ShopStaff::where('user_id', $user->id)->exists())) {
            return back()->with('error', $user->name.' has a shop or works for one, so cannot be iruali staff. Use a separate account for staff work.');
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
