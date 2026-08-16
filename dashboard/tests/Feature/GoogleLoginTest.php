<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => '/auth/google/callback',
        ]);
    }

    private function makeFreePlan(): Plan
    {
        return Plan::firstOrCreate(['slug' => 'free-daily'], [
            'name' => 'Free Harian',
            'total_tokens' => 7000000,
            'daily_limit_tokens' => 7000000,
            'duration_hours' => null,
            'price_usd' => 0,
            'price_idr' => 0,
            'is_active' => true,
            'resets_daily' => true,
        ]);
    }

    private function fakeGoogleUser(string $email, string $name, string $id = '123'): void
    {
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]));
    }

    public function test_google_login_creates_new_user_with_free_plan(): void
    {
        $this->makeFreePlan();
        $this->fakeGoogleUser('budi@gmail.com', 'Budi Google');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'budi@gmail.com', 'name' => 'Budi Google']);
        $user = User::where('email', 'budi@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('active', $user->status);
        // User baru otomatis mendapat plan gratis harian (sama seperti register biasa).
        $this->assertSame(1, $user->activePlans()->where('resets_daily', true)->count());
    }

    public function test_google_login_logs_in_existing_user_without_duplicate(): void
    {
        $this->makeFreePlan();
        $user = User::factory()->create(['email' => 'existing@gmail.com']);

        $this->fakeGoogleUser('existing@gmail.com', 'Nama Baru');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('email', 'existing@gmail.com')->count());
    }

    public function test_google_login_rejects_suspended_user(): void
    {
        User::factory()->create(['email' => 'suspended@gmail.com', 'status' => 'suspended']);
        $this->fakeGoogleUser('suspended@gmail.com', 'X');

        $this->get(route('google.callback'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_google_callback_unavailable_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('google.callback'))->assertNotFound();
    }
}
