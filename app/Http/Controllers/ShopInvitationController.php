<?php

namespace App\Http\Controllers;

use App\Models\ShopStaffInvitation;
use App\Models\User;
use App\Services\ShopStaffService;
use App\Support\CurrentShop;
use App\Support\ShopStaffAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The emailed invitation to a shop's staff (a signed link). With an account for the invited email,
 * they sign in with it and accept; without one they make one here (name and password; the link
 * confirms the email). Used, withdrawn, run-out and wrong-account links say so plainly.
 */
class ShopInvitationController extends Controller
{
    public function __construct(protected ShopStaffService $staff) {}

    public function show(Request $request, int $invitation, string $token)
    {
        $state = $this->state($request, $invitation, $token);

        // Signing in brings them straight back to this page
        if ($state['state'] === 'sign_in') {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('shop-invitations.show', $state);
    }

    public function accept(Request $request, int $invitation, string $token)
    {
        $state = $this->state($request, $invitation, $token);
        $invite = $state['invitation'];

        if ($state['state'] === 'sign_in') {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login')->with('info', __('Sign in as :email to accept the invitation.', ['email' => $invite?->email]));
        }
        if (! in_array($state['state'], ['accept', 'create_account'], true) || ! $invite) {
            return redirect($request->fullUrl());
        }

        if ($state['state'] === 'create_account') {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
                'agree_terms' => 'accepted',
            ], ['agree_terms.accepted' => __('You must accept the terms and conditions.')]);

            try {
                $user = $this->staff->createAccount($invite, $data['name'], $data['password']);
            } catch (UniqueConstraintViolationException) {
                return redirect($request->fullUrl()); // the account was made a moment ago: sign in with it
            }
            Auth::login($user);
            $request->session()->regenerate();
        } else {
            $user = $request->user();
        }

        try {
            $this->staff->accept($invite, $user);
        } catch (ValidationException $e) {
            return redirect($request->fullUrl())->withErrors($e->errors());
        }

        return redirect()->route('seller.dashboard')->with('success', __('Welcome to :shop. You are on the staff as a :role.', ['shop' => $state['shopName'], 'role' => mb_strtolower($invite->roleLabel())]));
    }

    /**
     * What the link can do for whoever opened it.
     *
     * @return array{state: string, invitation: ?ShopStaffInvitation, shopName: string, roleLabel: string, roleDescription: string, signedInAs: ?string, isMember: bool, actionUrl: string}
     */
    protected function state(Request $request, int $id, string $token): array
    {
        $invitation = ShopStaffInvitation::with(['shop', 'inviter'])->find($id);
        $user = $request->user();

        $state = $this->staff->invitationProblem($invitation, $token);
        if ($state === null && $invitation) {
            $email = ShopStaffInvitation::normalizeEmail((string) $invitation->email);
            $account = $user instanceof User ? $user : $this->staff->accountFor($email);
            $state = match (true) {
                $user instanceof User && ShopStaffInvitation::normalizeEmail((string) $user->email) !== $email => 'wrong_account',
                $account !== null && $invitation->shop !== null => $this->staff->joinBlock($account, $invitation->shop) ?? ($user ? 'accept' : 'sign_in'),
                default => 'create_account',
            };
        }

        // A link that is not (or no longer) this invitation's shows nothing about it
        $shown = $state === 'invalid' ? null : $invitation;

        return [
            'state' => $state,
            'invitation' => $shown,
            'shopName' => $shown?->shop?->shopName() ?? __('the shop'),
            'roleLabel' => $shown ? $shown->roleLabel() : '',
            'roleDescription' => $shown ? ShopStaffAccess::roleDescription((string) $shown->role) : '',
            'signedInAs' => $user?->email,
            // Already on this shop's staff (e.g. opening a used link again): the Seller Centre is one click away
            'isMember' => $user instanceof User && $shown && (int) CurrentShop::membershipOf($user)?->shop_id === (int) $shown->shop_id,
            'actionUrl' => $request->fullUrl(),
        ];
    }
}
