<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboxMessage;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportAttachmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'assignedAdmin']);
        foreach (['status', 'category', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->input('assigned') === 'unassigned') {
            $query->whereNull('assigned_admin_id');
        } elseif ($request->filled('assigned')) {
            $query->where('assigned_admin_id', $request->integer('assigned'));
        }
        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(fn (Builder $q) => $q->where('ticket_number', 'like', "%{$term}%")->orWhere('subject', 'like', "%{$term}%")->orWhere('request_reference', 'like', "%{$term}%")->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$term}%")));
        }
        $attention = fn () => SupportTicket::whereIn('status', ['awaiting_support', 'open'])->where(fn (Builder $q) => $q->whereNull('last_admin_read_at')->orWhereColumn('last_user_message_at', '>', 'last_admin_read_at'));

        return view('admin.support.index', ['tickets' => $query->latest('last_message_at')->paginate(25)->withQueryString(), 'admins' => User::where('is_admin', true)->orderBy('name')->get(), 'stats' => ['open' => SupportTicket::whereIn('status', ['awaiting_support', 'awaiting_user', 'open'])->count(), 'awaiting' => $attention()->count(), 'urgent' => SupportTicket::where('priority', 'urgent')->whereNotIn('status', ['resolved', 'closed'])->count(), 'unassigned' => SupportTicket::whereNull('assigned_admin_id')->whereNotIn('status', ['resolved', 'closed'])->count()]]);
    }

    public function show(Request $request, SupportTicket $supportTicket)
    {
        $supportTicket->update(['last_admin_read_at' => now()]);
        $supportTicket->load(['messages.sender', 'messages.attachments', 'user', 'assignedAdmin', 'billingEvent', 'paymentOrder']);

        return view('admin.support.show', ['ticket' => $supportTicket, 'admins' => User::where('is_admin', true)->orderBy('name')->get()]);
    }

    public function reply(Request $request, SupportTicket $supportTicket, SupportAttachmentService $attachments)
    {
        abort_if($supportTicket->status === 'closed', 422, 'Tiket sudah ditutup.');
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:10000'], 'is_internal' => ['nullable', 'boolean'], 'attachments' => ['nullable', 'array', 'max:3'], 'attachments.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']]);
        $attachments->transaction(function (array &$stored) use ($request, $supportTicket, $data, $attachments) {
            $internal = (bool) ($data['is_internal'] ?? false);
            $message = $supportTicket->messages()->create(['sender_id' => $request->user()->id, 'sender_role' => 'admin', 'body' => $data['body'], 'is_internal' => $internal]);
            $attachments->attach($message, $supportTicket, $request->user(), $request->file('attachments', []), $stored);
            $updates = ['last_admin_read_at' => now()];
            if (! $internal) {
                $updates += ['status' => 'awaiting_user', 'last_message_at' => now(), 'last_admin_message_at' => now(), 'resolved_at' => null];
                InboxMessage::firstOrCreate(['dedupe_key' => "support:reply:{$message->id}"], ['user_id' => $supportTicket->user_id, 'sender_id' => $request->user()->id, 'subject' => "Balasan tiket {$supportTicket->ticket_number}", 'body' => 'Tim dukungan telah membalas tiket Anda. Buka Support Center untuk melihat balasan.']);
            }
            $supportTicket->update($updates);
        });

        return back()->with('success', ! empty($data['is_internal']) ? 'Catatan internal ditambahkan.' : 'Balasan terkirim.');
    }

    public function attachment(SupportTicket $supportTicket, SupportAttachment $supportAttachment)
    {
        abort_unless($supportAttachment->message()->where('ticket_id', $supportTicket->id)->exists(), 404);
        abort_unless($supportAttachment->disk === 'local' && Str::startsWith($supportAttachment->path, 'support/') && Storage::disk('local')->exists($supportAttachment->path), 404);

        return Storage::disk('local')->response($supportAttachment->path, $supportAttachment->original_name, ['Content-Type' => $supportAttachment->mime_type, 'Content-Disposition' => 'inline; filename="'.addslashes($supportAttachment->original_name).'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['awaiting_support', 'awaiting_user', 'open', 'resolved', 'closed'])], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'category' => ['required', Rule::in(['technical', 'billing', 'payment', 'account', 'api_key', 'model', 'other'])], 'assigned_admin_id' => ['nullable', Rule::exists('users', 'id')->where('is_admin', true)]]);
        $oldStatus = $supportTicket->status;
        $data['resolved_at'] = $data['status'] === 'resolved' ? ($supportTicket->resolved_at ?? now()) : null;
        $data['closed_at'] = $data['status'] === 'closed' ? ($supportTicket->closed_at ?? now()) : null;
        $supportTicket->update($data);
        if ($data['status'] === 'resolved' && $oldStatus !== 'resolved') {
            InboxMessage::firstOrCreate(['dedupe_key' => "support:resolved:{$supportTicket->id}"], ['user_id' => $supportTicket->user_id, 'sender_id' => $request->user()->id, 'subject' => "Tiket {$supportTicket->ticket_number} diselesaikan", 'body' => 'Tiket dukungan Anda telah ditandai selesai. Buka Support Center jika perlu membalas dan membuka kembali tiket.']);
        }

        return back()->with('success', 'Tiket diperbarui.');
    }
}
