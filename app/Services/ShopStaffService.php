<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Role;
use App\Models\ShopStaff;
use App\Models\ShopStaffInvitation;
use App\Models\User;
use App\Notifications\ShopStaffInvited;
use App\Support\Audit;
use App\Support\CurrentShop;
use App\Support\ShopStaffAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shop staff (Seller Centre → Staff): inviting people by email, their joining through the signed
 * link, changing a role, removing someone and the owner's two-step sign-in rule. Every change is in
 * the audit log. One shop per staff account: shop owners, other shops' staff and iruali's own team
 * cannot join a shop's staff.
 */
class ShopStaffService
{
    /**
     * Invite someone by email, or send a still-open invitation to the same address again (with a new
     * link and 7 more days). Returns the invitation; the link goes out by email only.
     *
     * @throws ValidationException when the address cannot be invited, the shop is full or has sent too many
     */
    public function invite(User $shop, User $inviter, string $email, string $role): ShopStaffInvitation
    {
        $email = ShopStaffInvitation::normalizeEmail($email);
        abort_unless(ShopStaffAccess::isRole($role), 422);

        // Every attempt counts, refused ones too, so addresses cannot be tried one after another
        $key = 'shop-staff-invites:'.$shop->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('shop_staff.invitations_per_hour', 10))) {
            throw ValidationException::withMessages(['email' => __('You have sent a lot of invitations. Please try again in an hour.')]);
        }
        RateLimiter::hit($key, 3600);

        if (($reason = $this->inviteBlock($shop, $email)) !== null) {
            throw ValidationException::withMessages(['email' => $reason]);
        }

        $open = ShopStaffInvitation::query()->pending()->where('shop_id', $shop->id)->where('email', $email)->first();
        $max = (int) config('shop_staff.max_members', 20);
        $taken = ShopStaff::where('shop_id', $shop->id)->count()
            + ShopStaffInvitation::query()->pending()->where('shop_id', $shop->id)->count() - ($open ? 1 : 0);
        if ($taken >= $max) {
            throw ValidationException::withMessages(['email' => __('Your shop has :max staff members and invitations already. Remove someone or withdraw an invitation first.', ['max' => $max])]);
        }

        $token = Str::random(48);
        $invitation = $open ?? new ShopStaffInvitation;
        $invitation->forceFill([
            'shop_id' => $shop->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => ShopStaffInvitation::hashToken($token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays((int) config('shop_staff.invitation_days', 7)),
        ])->save();

        Audit::record('shop_staff.invited', $shop, ['email' => $email, 'role' => $role, 'again' => $open !== null, 'invitation_id' => $invitation->id]);
        // In the invited person's own language when they already have an account
        $locale = $this->accountFor($email)?->preferredLocale() ?? app()->getLocale();
        Notification::route('mail', $email)->notify((new ShopStaffInvited($invitation, $invitation->url($token)))->locale($locale));

        return $invitation;
    }

    /**
     * Why this address cannot be invited to this shop, or null when it can.
     */
    public function inviteBlock(User $shop, string $email): ?string
    {
        $email = ShopStaffInvitation::normalizeEmail($email);
        if ($email === ShopStaffInvitation::normalizeEmail((string) $shop->email)) {
            return __('That is your own account.');
        }

        $user = $this->accountFor($email);
        if (! $user) {
            return null;
        }

        return match ($this->joinBlock($user, $shop)) {
            'already_staff' => __('This person is already on your staff.'),
            'has_shop' => __('This person has a shop of their own on iruali, so they cannot join another shop\'s staff.'),
            'staff_elsewhere' => __('This person already works for another shop. A staff account belongs to one shop.'),
            'team' => __('This account cannot join a shop\'s staff. Invite a different email.'),
            default => null,
        };
    }

    /**
     * Why this account cannot join this shop's staff (already_staff, has_shop, staff_elsewhere, team),
     * or null when it can.
     */
    public function joinBlock(User $user, User $shop): ?string
    {
        $membership = CurrentShop::membershipOf($user);

        return match (true) {
            $membership !== null && (int) $membership->shop_id === (int) $shop->id => 'already_staff',
            $user->is($shop) || $user->hasRole('seller') || (bool) $user->is_seller => 'has_shop',
            $membership !== null => 'staff_elsewhere',
            $user->isStaff() => 'team',
            default => null,
        };
    }

    /**
     * What stands between this invitation and joining: invalid, accepted, revoked, expired or
     * shop_closed (the link itself), or null when it can be accepted.
     */
    public function invitationProblem(?ShopStaffInvitation $invitation, string $token): ?string
    {
        if (! $invitation || ! $invitation->matchesToken($token)) {
            return 'invalid';
        }
        $status = $invitation->status();
        if ($status !== 'pending') {
            return $status;
        }
        $shop = $invitation->shop;
        if (! $shop || ! $shop->hasRole('seller') || $shop->status === 'suspended') {
            return 'shop_closed';
        }

        return null;
    }

    /**
     * The account an invitation's email belongs to, if there is one.
     */
    public function accountFor(string $email): ?User
    {
        return User::where('email', ShopStaffInvitation::normalizeEmail($email))->first();
    }

    /**
     * The invited person joins the shop's staff. Checked again with the invitation row locked, so the
     * link can be used once only.
     *
     * @throws ValidationException when the invitation or the account cannot be used any more
     */
    public function accept(ShopStaffInvitation $invitation, User $user): ShopStaff
    {
        $member = DB::transaction(function () use ($invitation, $user) {
            $locked = ShopStaffInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => __('This invitation has already been used or withdrawn.')]);
            }
            $shop = $locked->shop;
            $reason = $shop ? $this->joinBlock($user, $shop) : 'shop_closed';
            if ($shop === null || $reason !== null) {
                throw ValidationException::withMessages(['invitation' => $reason === 'already_staff' ? __('You are already on this shop\'s staff.') : __('This account cannot join this shop\'s staff.')]);
            }

            $member = new ShopStaff(['role' => $locked->role]);
            try {
                $member->forceFill(['shop_id' => $shop->id, 'user_id' => $user->id, 'invited_by' => $locked->invited_by])->save();
            } catch (UniqueConstraintViolationException) {
                // Joined another shop at the same moment: one shop per staff account
                throw ValidationException::withMessages(['invitation' => __('This account cannot join this shop\'s staff.')]);
            }
            $locked->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();

            return $member;
        });

        Audit::record('shop_staff.joined', $member->shop, ['user_id' => $user->id, 'email' => $user->email, 'role' => $member->role, 'invitation_id' => $invitation->id]);

        return $member;
    }

    /**
     * A new account for someone invited by email: their address is confirmed by the link they used.
     */
    public function createAccount(ShopStaffInvitation $invitation, string $name, string $password): User
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        $user = User::create([
            'name' => $name,
            'email' => ShopStaffInvitation::normalizeEmail((string) $invitation->email),
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
            'referral_code' => $code,
            'preferred_language' => app()->getLocale(),
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer'])->id);

        // Orders placed as a guest with this email now show under My Orders, as at sign-up
        Order::whereNull('user_id')->where('guest_email', $user->email)->update(['user_id' => $user->id]);

        return $user;
    }

    public function changeRole(ShopStaff $member, string $role): void
    {
        abort_unless(ShopStaffAccess::isRole($role), 422);
        $from = $member->role;
        if ($from === $role) {
            return;
        }

        $member->forceFill(['role' => $role])->save();
        Audit::record('shop_staff.role_changed', $member->shop, ['user_id' => $member->user_id, 'from' => $from, 'to' => $role]);
    }

    /**
     * They lose access on their next request (CurrentShop is worked out on every request).
     */
    public function remove(ShopStaff $member): void
    {
        $changes = ['user_id' => $member->user_id, 'email' => $member->user?->email, 'role' => $member->role];
        $shop = $member->shop;
        $member->delete();

        Audit::record('shop_staff.removed', $shop, $changes);
    }

    public function revoke(ShopStaffInvitation $invitation): void
    {
        if (! $invitation->isPending()) {
            return;
        }

        $invitation->forceFill(['revoked_at' => now()])->save();
        Audit::record('shop_staff.invitation_revoked', $invitation->shop, ['email' => $invitation->email, 'role' => $invitation->role, 'invitation_id' => $invitation->id]);
    }

    /**
     * The owner asks their staff to use two-step sign-in before they open the Seller Centre.
     */
    public function requireTwoFactor(User $shop, bool $on): void
    {
        $shop->forceFill(['staff_require_two_factor' => $on])->save();
        if ($shop->wasChanged('staff_require_two_factor')) {
            Audit::record('shop_staff.two_factor', $shop, ['required' => $on]);
        }
    }
}
