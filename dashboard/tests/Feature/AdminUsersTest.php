<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\InboxMessage;
use App\Models\Transaction;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_DOMAIN = 'http://admin.azkia.cloud';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16250]], 200),
            '127.0.0.1:8001/*' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    public function test_users_list_with_search_and_stats(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.0005,
            'status_code' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users', ['search' => 'budi']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('budi@example.com');
    }

    public function test_user_detail_page_loads(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $key = ApiKey::create([
            'name' => 'Key 1',
            'prefix' => 'azkia_',
            'user_id' => $user->id,
            'key_hash' => str_repeat('a', 64),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'topup',
            'amount' => 10,
            'balance_after' => 10,
            'currency' => 'USD',
            'status' => 'completed',
        ]);
        UsageLog::create([
            'user_id' => $user->id,
            'api_key_id' => $key->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 5,
            'output_tokens' => 5,
            'cost' => 0.0001,
            'status_code' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Key 1')
            ->assertSee('topup');
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Lama', 'is_admin' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Baru',
                'email' => $user->email,
                'is_admin' => '1',
                'password' => 'rahasia123',
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Baru', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $key = ApiKey::create([
            'name' => 'Key 1',
            'prefix' => 'azkia_',
            'user_id' => $user->id,
            'key_hash' => str_repeat('b', 64),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'topup',
            'amount' => 5,
            'balance_after' => 5,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
        $this->assertDatabaseMissing('transactions', ['user_id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_send_inbox_message_to_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.messages.store', $user), [
                'subject' => 'Informasi akun',
                'body' => 'Saldo Anda telah diperbarui.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inbox_messages', [
            'user_id' => $user->id,
            'sender_id' => $admin->id,
            'subject' => 'Informasi akun',
        ]);
    }

    public function test_user_can_only_read_their_own_inbox_message(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $message = InboxMessage::create([
            'user_id' => $user->id,
            'sender_id' => $admin->id,
            'subject' => 'Pesan baru',
            'body' => 'Isi pesan.',
        ]);

        $this->actingAs($other)
            ->patch(route('inbox.read', $message))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertOk()
            ->assertSee('Pesan baru');

        $this->actingAs($user)
            ->patch(route('inbox.read', $message))
            ->assertRedirect();

        $this->assertNotNull($message->refresh()->read_at);
    }

    public function test_user_can_only_delete_their_own_inbox_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $message = InboxMessage::create([
            'user_id' => $user->id,
            'subject' => 'Pesan pengguna',
            'body' => 'Isi pesan.',
        ]);

        $this->actingAs($other)
            ->delete(route('inbox.destroy', $message))
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('inbox.destroy', $message))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inbox_messages', ['id' => $message->id]);
    }

    public function test_user_can_delete_all_their_messages_without_deleting_other_users_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        InboxMessage::create(['user_id' => $user->id, 'subject' => 'Satu', 'body' => 'Pesan satu.']);
        InboxMessage::create(['user_id' => $user->id, 'subject' => 'Dua', 'body' => 'Pesan dua.']);
        $otherMessage = InboxMessage::create(['user_id' => $other->id, 'subject' => 'Lain', 'body' => 'Pesan lain.']);

        $this->actingAs($user)
            ->delete(route('inbox.destroy-all'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('inbox_messages', ['user_id' => $user->id]);
        $this->assertDatabaseHas('inbox_messages', ['id' => $otherMessage->id]);
    }
}
