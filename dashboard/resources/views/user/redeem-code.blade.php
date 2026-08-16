@extends('layouts.app')

@section('content')
<div class="top"><div><h2>{{ __('dashboard.pages.redeem_code.heading') }}</h2></div></div>
@if(session('success'))<div class="card" style="border-color:var(--green-line);color:var(--green-ink);margin-bottom:14px">{{ session('success') }}</div>@endif
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
<div class="card" style="max-width:620px">
<form method="post" action="{{ route('redeem-codes.store') }}">@csrf
<input type="hidden" name="idempotency" value="{{ old('idempotency', $idempotency) }}">
<label class="muted">{{ __('dashboard.pages.redeem_code.code_label') }}</label><input name="code" value="{{ old('code') }}" placeholder="AZK-XXXX-XXXX-XXXX" autocomplete="off" required>
<button>{{ __('dashboard.pages.redeem_code.redeem') }}</button>
</form>
</div>
@endsection
