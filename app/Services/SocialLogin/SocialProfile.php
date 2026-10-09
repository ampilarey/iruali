<?php

namespace App\Services\SocialLogin;

/**
 * Who signed in at a provider: its stable id for the person (subject), the email it gave and
 * whether it vouches that the person owns that email, and their name when it shared one.
 */
final class SocialProfile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
    ) {}
}
