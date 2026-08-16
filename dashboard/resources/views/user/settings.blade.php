@extends('layouts.app')

@section('content')
<style>
    .set-card .card{padding:22px;max-width:560px}
    .set-card h3{margin:0 0 4px;font-size:15px;font-weight:800;letter-spacing:-.01em}
    .set-card .sub{color:var(--muted);font-size:13px;margin:0 0 16px}
    .set-card label{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:12px 0 4px}
    .set-card input{margin:0 0 4px}
    .set-actions{display:flex;align-items:center;gap:12px;margin-top:16px}
    .set-actions .muted{font-size:12.5px}
    .set-success{background:var(--green-soft);border:1px solid var(--green-line);color:var(--green-ink);border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px}
    .set-error{background:var(--red-soft);border:1px solid var(--red-line);color:var(--red-ink);border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px}
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.settings.heading') }}</h2><p>{{ __('dashboard.pages.settings.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.common.account') }}</span>
</div>

@if(session('success'))
    <div class="set-success">{{ session('success') }}</div>
@endif

<div class="set-card">
    <div class="card">
        <h3>{{ __('dashboard.pages.settings.profile') }}</h3>
        <p class="sub">{{ __('dashboard.pages.settings.profile_hint') }}</p>
        <form method="post" action="{{ route('settings.profile') }}">
            @csrf @method('patch')
            <label>{{ __('dashboard.common.name') }}</label>
            <input name="name" value="{{ old('name', $user->name) }}" required maxlength="255" placeholder="{{ __('dashboard.pages.settings.full_name') }}">
            @error('name')<div class="set-error">{{ $message }}</div>@enderror

            <label>{{ __('dashboard.pages.settings.email') }}</label>
            <input name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" placeholder="nama@email.com">
            @error('email')<div class="set-error">{{ $message }}</div>@enderror

            <div class="set-actions">
                <button>{{ __('dashboard.pages.settings.save_profile') }}</button>
                <span class="muted">{{ __('dashboard.pages.settings.account_id', ['id' => $user->id]) }}</span>
            </div>
        </form>
    </div>
</div>
@endsection
