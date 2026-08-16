@extends('layouts.app')

@section('content')
<div class="auth-bg">
    <div class="auth-card">
        <a class="auth-back" href="/">← Beranda</a>
        <div class="auth-top">
            <img class="logo auth-logo platform-logo" src="{{ asset('azkia-logo.png') }}" alt="Logo Azkia Router">
            <h2>Create account</h2>
            <p>Mulai gunakan OpenAI-compatible gateway</p>
        </div>
        @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('register.store') }}">
            @csrf
            <label class="muted">Nama</label>
            <input name="name" placeholder="Nama lengkap" value="{{ old('name') }}" required autofocus>
            <label class="muted">Email</label>
            <input name="email" type="email" placeholder="email@domain.com" value="{{ old('email') }}" required>
            <label class="muted">Password</label>
            <input name="password" type="password" placeholder="Minimal 8 karakter" required>
            <label class="muted">Konfirmasi Password</label>
            <input name="password_confirmation" type="password" placeholder="Ulangi password" required>
            <button class="btn">Create account</button>
        </form>
        @if(config('services.google.client_id'))
        <div style="display:flex;align-items:center;gap:10px;margin:18px 0 0;color:var(--muted);font-size:12px"><span style="flex:1;height:1px;background:var(--line)"></span>atau<span style="flex:1;height:1px;background:var(--line)"></span></div>
        <a class="btn secondary" href="{{ route('google.redirect') }}" style="width:100%;margin-top:16px;text-decoration:none">
            <svg viewBox="0 0 24 24" width="16" height="16" style="flex:0 0 auto" aria-hidden="true"><path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/></svg>
            Daftar dengan Google
        </a>
        @endif
        <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Login</a></p>
    </div>
</div>
@endsection
