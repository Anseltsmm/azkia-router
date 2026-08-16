<?php

namespace App\Http\Controllers;

use App\Models\BillingEvent;
use App\Models\PaymentOrder;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Services\SupportAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)->latest('last_message_at')->paginate(20);

        return view('user.support.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        return view('user.support.create', ['billingEvents' => BillingEvent::where('user_id', $request->user()->id)->latest()->limit(50)->get(), 'paymentOrders' => PaymentOrder::where('user_id', $request->user()->id)->latest()->limit(50)->get()]);
    }

    public function store(Request $request, SupportAttachmentService $attachments)
    {
        $data = $this->validated($request, true);
        $this->validateOwnership($request, $data);
        $ticket = $attachments->transaction(function (array &$stored) use ($request, $data, $attachments) {
            $now = now();
            $ticket = SupportTicket::create([...$data, 'ticket_number' => $this->ticketNumber(), 'user_id' => $request->user()->id, 'priority' => 'normal', 'status' => 'awaiting_support', 'last_message_at' => $now, 'last_user_message_at' => $now, 'last_user_read_at' => $now]);
            $message = $ticket->messages()->create(['sender_id' => $request->user()->id, 'sender_role' => 'user', 'body' => $data['body'], 'is_internal' => false]);
            $attachments->attach($message, $ticket, $request->user(), $request->file('attachments', []), $stored);

            return $ticket;
        });

        return redirect()->route('support.show', $ticket)->with('success', 'Tiket dukungan berhasil dibuat.');
    }

    public function show(Request $request, SupportTicket $supportTicket)
    {
        $this->own($request, $supportTicket);
        $supportTicket->update(['last_user_read_at' => now()]);
        $supportTicket->load(['messages' => fn ($q) => $q->where('is_internal', false)->with(['sender', 'attachments']), 'billingEvent', 'paymentOrder']);

        return view('user.support.show', ['ticket' => $supportTicket]);
    }

    public function reply(Request $request, SupportTicket $supportTicket, SupportAttachmentService $attachments)
    {
        $this->own($request, $supportTicket);
        abort_if($supportTicket->status === 'closed', 422, 'Tiket sudah ditutup.');
        $data = $request->validate($this->messageRules());
        $attachments->transaction(function (array &$stored) use ($request, $supportTicket, $data, $attachments) {
            $message = $supportTicket->messages()->create(['sender_id' => $request->user()->id, 'sender_role' => 'user', 'body' => $data['body'], 'is_internal' => false]);
            $attachments->attach($message, $supportTicket, $request->user(), $request->file('attachments', []), $stored);
            $supportTicket->update(['status' => 'awaiting_support', 'resolved_at' => null, 'last_message_at' => now(), 'last_user_message_at' => now(), 'last_user_read_at' => now()]);
        });

        return back()->with('success', 'Balasan terkirim.');
    }

    public function attachment(Request $request, SupportTicket $supportTicket, SupportAttachment $supportAttachment)
    {
        $this->own($request, $supportTicket);
        abort_unless($supportAttachment->message()->where('ticket_id', $supportTicket->id)->where('is_internal', false)->exists(), 404);

        return $this->fileResponse($supportAttachment);
    }

    private function validated(Request $request, bool $withBody): array
    {
        return $request->validate([...['subject' => ['required', 'string', 'min:4', 'max:180'], 'category' => ['required', Rule::in(['technical', 'billing', 'payment', 'account', 'api_key', 'model', 'other'])], 'request_reference' => ['nullable', 'string', 'max:255'], 'billing_event_id' => ['nullable', 'uuid'], 'payment_order_id' => ['nullable', 'integer']], ...$this->messageRules($withBody)]);
    }

    private function messageRules(bool $required = true): array
    {
        return ['body' => [$required ? 'required' : 'nullable', 'string', 'min:2', 'max:10000'], 'attachments' => ['nullable', 'array', 'max:3'], 'attachments.*' => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120']];
    }

    private function fileResponse(SupportAttachment $attachment)
    {
        abort_unless($attachment->disk === 'local' && Str::startsWith($attachment->path, 'support/') && Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function validateOwnership(Request $request, array $data): void
    {
        if (! empty($data['billing_event_id'])) {
            abort_unless(BillingEvent::whereKey($data['billing_event_id'])->where('user_id', $request->user()->id)->exists(), 422);
        }
        if (! empty($data['payment_order_id'])) {
            abort_unless(PaymentOrder::whereKey($data['payment_order_id'])->where('user_id', $request->user()->id)->exists(), 422);
        }
    }

    private function own(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }

    private function ticketNumber(): string
    {
        do {
            $number = 'SUP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
