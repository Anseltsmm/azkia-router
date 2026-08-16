<?php

namespace Tests\Feature;

use App\Models\InboxMessage;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_and_only_view_own_ticket(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post(route('support.store'), ['subject' => 'Need technical help', 'category' => 'technical', 'body' => 'Please investigate this issue.'])->assertRedirect();
        $ticket = SupportTicket::first();
        $this->assertSame('awaiting_support', $ticket->status);
        $this->actingAs($other)->get(route('support.show', $ticket))->assertNotFound();
    }

    public function test_user_reply_reopens_resolved_ticket(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticket($user, ['status' => 'resolved', 'resolved_at' => now()]);
        $this->actingAs($user)->post(route('support.reply', $ticket), ['body' => 'The problem has returned.'])->assertRedirect();
        $this->assertSame('awaiting_support', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_admin_public_reply_notifies_once_and_internal_note_is_hidden(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $this->actingAs($admin)->post(route('admin.support.reply', $ticket), ['body' => 'Public support response.']);
        $this->assertSame('awaiting_user', $ticket->fresh()->status);
        $this->assertSame(1, InboxMessage::count());
        $this->actingAs($admin)->post(route('admin.support.reply', $ticket), ['body' => 'Private diagnostic note.', 'is_internal' => 1]);
        $this->assertSame(1, InboxMessage::count());
        $this->actingAs($user)->get(route('support.show', $ticket))->assertOk()->assertSee('Public support response.')->assertDontSee('Private diagnostic note.');
    }

    public function test_admin_can_update_ticket_and_resolved_notification_is_deduplicated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $ticket = $this->ticket($user);
        $payload = ['status' => 'resolved', 'priority' => 'urgent', 'category' => 'billing', 'assigned_admin_id' => $admin->id];
        $this->actingAs($admin)->patch(route('admin.support.update', $ticket), $payload)->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.support.update', $ticket), $payload)->assertRedirect();
        $this->assertSame('urgent', $ticket->fresh()->priority);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertSame(1, InboxMessage::where('dedupe_key', "support:resolved:{$ticket->id}")->count());
    }

    public function test_private_image_attachment_upload_and_authorized_viewing(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner)->post(route('support.store'), ['subject' => 'Attachment test', 'category' => 'technical', 'body' => 'Please inspect image.', 'attachments' => [UploadedFile::fake()->image('screen.png')]])->assertRedirect();
        $ticket = SupportTicket::firstOrFail();
        $attachment = SupportAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('support_attachments', ['support_message_id' => $attachment->support_message_id, 'uploaded_by' => $owner->id, 'mime_type' => 'image/png']);
        $this->actingAs($owner)->get(route('support.attachments.show', [$ticket, $attachment]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($other)->get(route('support.attachments.show', [$ticket, $attachment]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.support.attachments.show', [$ticket, $attachment]))->assertOk();
    }

    public function test_internal_attachment_is_hidden_from_user_but_visible_to_admin(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->ticket($owner);
        $this->actingAs($admin)->post(route('admin.support.reply', $ticket), ['body' => 'Internal image.', 'is_internal' => 1, 'attachments' => [UploadedFile::fake()->image('internal.jpg')]])->assertRedirect();
        $attachment = SupportAttachment::firstOrFail();
        $this->actingAs($owner)->get(route('support.attachments.show', [$ticket, $attachment]))->assertNotFound();
        $this->actingAs($owner)->get(route('support.show', $ticket))->assertDontSee($attachment->original_name);
        $this->actingAs($admin)->get(route('admin.support.attachments.show', [$ticket, $attachment]))->assertOk();
    }

    public function test_invalid_and_oversize_attachments_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        foreach ([UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml'), UploadedFile::fake()->create('document.txt', 10, 'text/plain'), UploadedFile::fake()->image('large.jpg')->size(5121)] as $file) {
            $this->actingAs($user)->post(route('support.store'), ['subject' => 'Invalid attachment', 'category' => 'technical', 'body' => 'This must fail.', 'attachments' => [$file]])->assertSessionHasErrors('attachments.0');
        }
        $this->assertSame(0, SupportAttachment::count());
    }

    private function ticket(User $user, array $attributes = []): SupportTicket
    {
        $ticket = SupportTicket::create([...['ticket_number' => 'SUP-TEST-'.strtoupper(fake()->bothify('??##')), 'user_id' => $user->id, 'subject' => 'Test support ticket', 'category' => 'technical', 'priority' => 'normal', 'status' => 'awaiting_support', 'last_message_at' => now(), 'last_user_message_at' => now()], ...$attributes]);
        SupportMessage::create(['ticket_id' => $ticket->id, 'sender_id' => $user->id, 'sender_role' => 'user', 'body' => 'Initial user message.', 'is_internal' => false]);

        return $ticket;
    }
}
