@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Edit User</h2><p>Perbarui data akun <strong>{{ $user->name }}</strong>.</p></div>
    <span class="pill">#{{ $user->id }}</span>
</div>

@include('admin._alerts')

<div class="section card" style="max-width:560px">
    <form method="post" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('patch')
        <label class="muted">Name</label><input name="name" value="{{ old('name', $user->name) }}" required>
        <label class="muted">Email</label><input name="email" type="email" value="{{ old('email', $user->email) }}" required>
        <label class="muted">Role</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);cursor:pointer;margin:2px 0 12px"><input type="hidden" name="is_admin" value="0"><input type="checkbox" name="is_admin" value="1" @checked($user->is_admin) style="width:16px;height:16px;margin:0;flex:0 0 auto"> Admin (akses panel admin)</label>
        <label class="muted">Password baru <span style="text-transform:none;letter-spacing:0;font-weight:400">(kosongkan jika tidak diganti)</span></label>
        <input name="password" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password">
        <div style="display:flex;gap:8px;margin-top:6px">
            <button>Simpan Perubahan</button>
            <a class="btn secondary" href="{{ route('admin.users.show', $user) }}" style="text-decoration:none">Batal</a>
        </div>
    </form>
</div>
@endsection
