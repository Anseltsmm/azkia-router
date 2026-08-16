<?php

namespace App\Http\Controllers;

use App\Models\InboxMessage;
use Illuminate\Support\Facades\Auth;

class InboxController extends Controller
{
    public function index()
    {
        $query = InboxMessage::where('user_id', Auth::id());

        return view('user.inbox', [
            'messages' => (clone $query)->with('sender')->latest()->paginate(20),
            'unreadInboxCount' => (clone $query)->whereNull('read_at')->count(),
        ]);
    }

    public function read(InboxMessage $inboxMessage)
    {
        abort_unless($inboxMessage->user_id === Auth::id(), 403);

        if (! $inboxMessage->read_at) {
            $inboxMessage->update(['read_at' => now()]);
        }

        return back();
    }

    public function readAll()
    {
        InboxMessage::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', __('dashboard.flash.messages_read'));
    }

    public function destroy(InboxMessage $inboxMessage)
    {
        abort_unless($inboxMessage->user_id === Auth::id(), 403);

        $inboxMessage->delete();

        return back()->with('success', __('dashboard.flash.message_deleted'));
    }

    public function destroyAll()
    {
        InboxMessage::where('user_id', Auth::id())->delete();

        return back()->with('success', __('dashboard.flash.messages_deleted'));
    }
}
