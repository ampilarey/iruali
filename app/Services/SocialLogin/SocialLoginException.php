<?php

namespace App\Services\SocialLogin;

use RuntimeException;

/**
 * A social sign-in that cannot go ahead. The message is shown to the customer on the sign-in page.
 */
class SocialLoginException extends RuntimeException
{
    /** The account that has this email typed on it, never confirmed (unprovenEmail()). */
    public ?\App\Models\User $unprovenAccount = null;

    public string $unprovenEmail = '';

    /**
     * The provider vouched for an email that sits, unconfirmed, on an account that is someone's by its
     * phone or another sign-in. Nobody is signed in: the person chooses ("it is my account" / "it is
     * not"), as with a code by email (UnprovenEmailController).
     */
    public static function unprovenEmail(\App\Models\User $account, string $email): self
    {
        $e = new self(__('This email is on another account.'));
        $e->unprovenAccount = $account;
        $e->unprovenEmail = $email;

        return $e;
    }

    public static function failed(string $provider): self
    {
        return new self(__('We could not sign you in with :provider. Please try again.', ['provider' => $provider]));
    }

    public static function expired(): self
    {
        return new self(__('That sign-in took too long or was started in another window. Please try again.'));
    }

    public static function cancelled(string $provider): self
    {
        return new self(__('Sign-in with :provider was cancelled.', ['provider' => $provider]));
    }

    public static function noEmail(string $provider): self
    {
        return new self(__(':provider did not share your email address. Allow it and try again, or sign up with your email.', ['provider' => $provider]));
    }

    public static function emailInUse(string $email): self
    {
        return new self(__('An iruali account already uses :email. Sign in with your email and password instead.', ['email' => $email]));
    }

    public static function staff(string $provider): self
    {
        return new self(__('Staff accounts cannot sign in with :provider. Use your password.', ['provider' => $provider]));
    }

    public static function refused(string $message): self
    {
        return new self($message);
    }
}
