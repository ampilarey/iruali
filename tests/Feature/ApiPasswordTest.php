<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * The app's password routes: forgot, reset, change and sign out.
 */
class ApiPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected const STRONG = 'N3w!Strong#Pass';

    public function test_forgot_password_gives_the_same_answer_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/forgot-password', ['email' => 'known@example.com'])->assertOk();
        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_refuses_a_weak_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'password1', 'password_confirmation' => 'password1',
        ])->assertStatus(422);

        $this->assertFalse(Hash::check('password1', $user->fresh()->password));
    }

    public function test_reset_signs_out_every_app_and_browser(): void
    {
        $user = User::factory()->create();
        $user->createToken('stolen');
        $oldRemember = $user->remember_token;
        $token = Password::createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => self::STRONG, 'password_confirmation' => self::STRONG,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
    }

    public function test_change_password_keeps_this_app_signed_in_and_signs_out_the_others(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old!Pass#2026')]);
        $other = $user->createToken('other-phone')->plainTextToken;
        $mine = $user->createToken('this-phone')->plainTextToken;

        $this->withToken($mine)->postJson('/api/v1/user/change-password', [
            'current_password' => 'wrong', 'new_password' => self::STRONG, 'new_password_confirmation' => self::STRONG,
        ])->assertStatus(400);

        $this->withToken($mine)->postJson('/api/v1/user/change-password', [
            'current_password' => 'Old!Pass#2026', 'new_password' => self::STRONG, 'new_password_confirmation' => self::STRONG,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
        $this->assertSame(['this-phone'], $user->tokens()->pluck('name')->all());
        $this->assertNotSame($other, $mine);
    }

    public function test_logout_deletes_only_this_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('other-phone');
        $mine = $user->createToken('this-phone')->plainTextToken;

        $this->withToken($mine)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(['other-phone'], $user->tokens()->pluck('name')->all());
    }
}
