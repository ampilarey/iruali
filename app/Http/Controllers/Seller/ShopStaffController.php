<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ShopStaff;
use App\Models\ShopStaffInvitation;
use App\Models\User;
use App\Services\ShopStaffService;
use App\Support\CurrentShop;
use App\Support\ShopStaffAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seller Centre → Staff (the owner only): who works in the shop's Seller Centre and in which role,
 * invitations by email, changing a role, removing someone (they lose access on their next request)
 * and asking staff to use two-step sign-in. Every change is in the audit log (ShopStaffService).
 */
class ShopStaffController extends Controller
{
    public function __construct(protected ShopStaffService $staff) {}

    public function index()
    {
        $shop = $this->shop();

        return view('seller.staff.index', [
            'shop' => $shop,
            'members' => ShopStaff::where('shop_id', $shop->id)->with(['user', 'inviter'])->orderBy('id')->get(),
            // Open and run-out invitations (a run-out one can be sent again); used and withdrawn ones drop off
            'invitations' => ShopStaffInvitation::where('shop_id', $shop->id)->whereNull('accepted_at')->whereNull('revoked_at')->with('inviter')->latest('id')->get(),
            'roles' => ShopStaffAccess::roles(),
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'role' => ['required', Rule::in(ShopStaffAccess::roles())],
        ]);

        $invitation = $this->staff->invite($this->shop(), $request->user(), $data['email'], $data['role']);

        return redirect()->route('seller.staff')->with('success', trans_choice('Invitation sent to :email. The link works for :count day.|Invitation sent to :email. The link works for :count days.', (int) config('shop_staff.invitation_days', 7), ['email' => $invitation->email, 'count' => (int) config('shop_staff.invitation_days', 7)]));
    }

    public function revoke(ShopStaffInvitation $invitation)
    {
        abort_unless((int) $invitation->shop_id === (int) $this->shop()->id, 404);

        $this->staff->revoke($invitation);

        return redirect()->route('seller.staff')->with('success', __('The invitation to :email is withdrawn. Its link no longer works.', ['email' => $invitation->email]));
    }

    public function update(Request $request, ShopStaff $member)
    {
        abort_unless((int) $member->shop_id === (int) $this->shop()->id, 404);
        $data = $request->validate(['role' => ['required', Rule::in(ShopStaffAccess::roles())]]);

        $this->staff->changeRole($member, $data['role']);

        return redirect()->route('seller.staff')->with('success', __(':name is now a :role.', ['name' => $member->user->name ?? __('This person'), 'role' => mb_strtolower(ShopStaffAccess::roleLabel($data['role']))]));
    }

    public function destroy(ShopStaff $member)
    {
        abort_unless((int) $member->shop_id === (int) $this->shop()->id, 404);
        $name = $member->user->name ?? __('This person');

        $this->staff->remove($member);

        return redirect()->route('seller.staff')->with('success', __(':name can no longer open your Seller Centre.', ['name' => $name]));
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['staff_require_two_factor' => 'required|boolean']);

        $this->staff->requireTwoFactor($this->shop(), (bool) $data['staff_require_two_factor']);

        return redirect()->route('seller.staff')->with('success', $data['staff_require_two_factor']
            ? __('Your staff now need two-step sign-in turned on before they can open the Seller Centre.')
            : __('Your staff can open the Seller Centre without two-step sign-in.'));
    }

    /**
     * The owner's shop. Staff never reach this page (config/shop_staff.php owner_only); this says so again.
     */
    protected function shop(): User
    {
        abort_if(CurrentShop::isStaff(), 403);

        return CurrentShop::get();
    }
}
