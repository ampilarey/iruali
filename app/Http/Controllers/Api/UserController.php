<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class UserController extends BaseController
{
    /**
     * Get user profile
     */
    public function profile()
    {
        $user = Auth::user();

        return $this->sendResponse(new UserResource($user), 'Profile retrieved successfully');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'phone' => ['nullable', 'string', 'max:20', new \App\Rules\PhoneNotTaken($user->id)],
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }
        if ($request->exists('phone') && $user->signsInWithPhoneOnly()
            && \App\Services\Sms\PhoneNumber::normalize($request->input('phone')) !== \App\Services\Sms\PhoneNumber::normalize($user->phone)) {
            return $this->sendValidationError(['phone' => [__('You sign in with this mobile number. Add an email to your account first, or ask us to change the number.')]]);
        }

        $user->update($request->only([
            'name', 'phone', 'address', 'city', 'state',
            'country', 'postal_code', 'date_of_birth', 'gender',
        ]));

        return $this->sendResponse(new UserResource($user), 'Profile updated successfully');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();

        // Check current password
        if (! Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password is incorrect');
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password),
            'has_password' => true,
        ])->save();

        // Everywhere else is signed out, as when the password is changed on the website
        // (User::endOtherSessions()): browser sessions, "remember me" and the other app sign-ins. This
        // app keeps its own.
        $current = $user->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? $current->id : null;
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();
        $user->tokens()->when($keep, fn ($q) => $q->whereKeyNot($keep))->delete();

        return $this->sendResponse([], 'Password changed successfully');
    }
}
