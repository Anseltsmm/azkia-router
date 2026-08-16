@if(session('success'))
    <div style="background:var(--green-soft);border:1px solid var(--green-line);color:var(--green-ink);border-radius:10px;padding:11px 14px;margin-bottom:14px;font-size:13.5px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="background:var(--red-soft);border:1px solid var(--red-line);color:var(--red-ink);border-radius:10px;padding:11px 14px;margin-bottom:14px;font-size:13.5px">⛔ {{ session('error') }}</div>
@endif
@if($errors->any())
    <div style="background:var(--red-soft);border:1px solid var(--red-line);color:var(--red-ink);border-radius:10px;padding:11px 14px;margin-bottom:14px;font-size:13.5px">
        <strong>Terjadi kesalahan:</strong>
        <ul style="margin:6px 0 0;padding-left:18px">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif
