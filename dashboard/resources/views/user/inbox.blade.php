@extends('layouts.app')

@section('content')
<style>
    .inbox-page-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
    .message-list{display:grid;gap:10px}
    .message-card{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-card);padding:17px 18px;box-shadow:var(--shadow-card)}
    .message-card.unread{border-left:4px solid var(--brand);background:var(--brand-soft)}
    .message-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .message-top h3{margin:0;font-size:14px;font-weight:750}
    .message-meta{color:var(--muted);font-size:11.5px;margin-top:3px}
    .message-body{margin:12px 0 0;color:var(--body);font-size:13.5px;white-space:pre-wrap;line-height:1.7}
    .message-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:13px}
    .inbox-head-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .inbox-page-empty{text-align:center;padding:50px 20px;color:var(--muted)}
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.inbox.heading') }}</h2></div>
    <div class="inbox-head-actions">
        @if($unreadInboxCount > 0)
        <form method="post" action="{{ route('inbox.read-all') }}">@csrf @method('patch')<button class="secondary">{{ __('dashboard.pages.inbox.mark_all') }}</button></form>
        @endif
        @if($messages->total() > 0)
        <form method="post" action="{{ route('inbox.destroy-all') }}" onsubmit="return confirm(@js(__('dashboard.pages.inbox.delete_all_confirm')))">@csrf @method('delete')<button class="danger">{{ __('dashboard.pages.inbox.delete_all') }}</button></form>
        @endif
    </div>
</div>

<div class="message-list">
    @forelse($messages as $message)
    <article class="message-card {{ $message->read_at ? '' : 'unread' }}" id="message-{{ $message->id }}">
        <div class="message-top">
            <div>
                <h3>{{ $message->subject }}</h3>
                <div class="message-meta">{{ __('dashboard.pages.inbox.from', ['date' => $message->created_at?->locale(app()->getLocale())->translatedFormat('d M Y H:i')]) }}</div>
            </div>
            <span class="badge {{ $message->read_at ? 'green' : 'amber' }}">{{ $message->read_at ? __('dashboard.status.read') : __('dashboard.status.new') }}</span>
        </div>
        <p class="message-body">{{ $message->body }}</p>
        <div class="message-actions">
            @if(!$message->read_at)
            <form method="post" action="{{ route('inbox.read', $message) }}">@csrf @method('patch')<button class="secondary">{{ __('dashboard.pages.inbox.mark_read') }}</button></form>
            @endif
            <form method="post" action="{{ route('inbox.destroy', $message) }}" onsubmit="return confirm(@js(__('dashboard.pages.inbox.delete_confirm')))">@csrf @method('delete')<button class="danger">{{ __('dashboard.common.delete') }}</button></form>
        </div>
    </article>
    @empty
    <div class="card inbox-page-empty">{{ __('dashboard.pages.inbox.empty') }}</div>
    @endforelse
</div>

<div class="section">{{ $messages->links() }}</div>
@endsection
