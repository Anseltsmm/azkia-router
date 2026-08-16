<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_english_and_locale_persists(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Build with one AI gateway')->assertSee('<html lang="en">', false);
    }

    public function test_user_can_switch_to_indonesian(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'id'])->assertRedirect();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Bangun dengan satu gerbang AI')->assertSee('<html lang="id">', false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_locale_choice_is_saved_in_cookie_and_persists_between_visits(): void
    {
        // Switch bahasa → response menyertakan cookie 'locale' (persisten 1 tahun).
        $response = $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $response->assertCookie('locale', 'en');

        // Kunjungan berikutnya (session baru, tanpa session locale) — cookie tetap
        // dipakai sehingga bahasa EN bertahan.
        $this->withCookie('locale', 'en')
            ->get(route('privacy'))
            ->assertOk()
            ->assertSee('<html lang="en">', false);

        // Switch balik ke ID → cookie ikut berubah.
        $this->withCookie('locale', 'en')
            ->post(route('locale.update'), ['locale' => 'id'])
            ->assertCookie('locale', 'id');
    }

    public function test_guest_can_switch_locale(): void
    {
        // Guest boleh ganti bahasa (landing page) — tidak lagi di-redirect ke login.
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', session('locale'));

        $this->post(route('locale.update'), ['locale' => 'id'])->assertRedirect();
        $this->assertSame('id', session('locale'));
    }

    public function test_admin_locale_is_unaffected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->withSession(['locale' => 'en'])->get('http://admin.azkia.cloud/')->assertOk()->assertSee('Admin Dashboard');
        $this->assertSame('id', app()->getLocale());
    }

    /**
     * Status pembayaran Tripay (EXPIRED/REFUND) punya key terjemahan di
     * kedua bahasa — key dinamis di billing/payment tidak boleh render literal.
     */
    public function test_all_payment_status_keys_exist_in_both_locales(): void
    {
        $statuses = ['paid', 'unpaid', 'failed', 'expired', 'refund', 'completed', 'pending'];

        foreach (['id', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($statuses as $status) {
                $translated = __('dashboard.status.'.$status);
                $this->assertNotSame('dashboard.status.'.$status, $translated, "[$locale] key status.$status tidak diterjemahkan");
                $this->assertNotSame('', $translated, "[$locale] key status.$status kosong");
            }
        }
    }
}
