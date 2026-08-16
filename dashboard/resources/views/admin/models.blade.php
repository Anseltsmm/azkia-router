@extends('layouts.app')

@section('content')
<style>
    .badge svg{width:13px;height:13px;display:block}
</style>
<div class="top">
    <div><h2>Models</h2><p>Daftarkan alias model publik yang bisa dipakai user (OpenAI-compatible).</p></div>
    <span class="pill">{{ $models->count() }} models</span>
</div>

@include('admin._alerts')

<div class="section card">
    <h3>Add Model</h3>
    <form method="post" action="{{ route('admin.models.store') }}" enctype="multipart/form-data" class="grid2" style="gap:0 22px">
        @csrf
        <div>
            <label class="muted">Provider</label><select name="provider_id"><option value="">Default env provider</option>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select>
            <label class="muted">Public Name</label><input name="public_name" placeholder="azkia/fast" required>
            <label class="muted">Upstream Name</label><input name="upstream_name" placeholder="gpt-4o-mini" required>
            <label class="muted">Ikon Model</label>
            <input name="icon" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="padding:6px">
            @error('icon')<div style="color:var(--red-ink);font-size:12.5px;margin-top:4px">{{ $message }}</div>@enderror
            <p class="muted" style="font-size:11.5px;margin:2px 0 0">PNG/JPG/SVG/WebP, maks 5 MB. Kosongkan untuk memakai ikon default.</p>
        </div>
        <div>
            <label class="muted">Kemampuan (multi-modal)</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px 14px;margin:4px 0 10px">
                @foreach(capability_options() as $val => $label)
                <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer"><input type="checkbox" name="capabilities[]" value="{{ $val }}" @checked($val === 'chat') style="width:15px;height:15px;margin:0;padding:0;flex:0 0 auto"> {{ $label }}</label>
                @endforeach
            </div>
            <label class="muted">Context Window</label><input name="context_window" type="number" min="1" placeholder="128000">
            <label class="muted">Rate Limit Per Menit (opsional — kosong = tanpa batas)</label><input name="rate_limit_per_minute" type="number" min="1" placeholder="60" value="{{ old('rate_limit_per_minute') }}">
            <div style="margin-top:12px"><button>Save Model</button></div>
        </div>
    </form>
</div>

<div class="section card">
    <h3>Daftar Model</h3>
    <div class="table-wrap"><table><tr><th>Ikon</th><th>Public</th><th>Upstream</th><th>Provider</th><th>Rate/Menit</th><th>Kemampuan</th><th>Status</th><th>Action</th></tr>
        @forelse($models as $model)
        <tr>
            <td>@if($model->icon_url)<img src="{{ $model->icon_url }}" alt="" style="width:26px;height:26px;border-radius:7px;object-fit:contain;border:1px solid var(--line);background:var(--soft)">@else<span class="muted" style="font-size:11px">—</span>@endif</td>
            <td><strong>{{ $model->public_name }}</strong></td>
            <td style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px">{{ $model->upstream_name }}</td>
            <td>{{ $model->provider?->name ?? 'default env' }}</td>
            <td>{{ $model->rate_limit_per_minute ? $model->rate_limit_per_minute.'/menit' : '—' }}</td>
            <td>@foreach(collect($model->capabilities ?: [strtolower((string) $model->type)])->unique() as $cap)<span class="badge" title="{{ $cap }}" style="margin:0 3px 3px 0;padding:4px 6px;line-height:0">{!! capability_icon($cap) !!}</span>@endforeach</td>
            <td><span class="badge {{ $model->is_active ? 'green' : 'red' }}">{{ $model->is_active ? 'active' : 'off' }}</span></td>
            <td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn secondary" href="{{ route('admin.models.edit', $model) }}" style="text-decoration:none">Edit</a><form method="post" action="{{ route('admin.models.toggle', $model) }}">@csrf @method('patch')<button class="secondary">{{ $model->is_active ? 'Disable' : 'Enable' }}</button></form><form method="post" action="{{ route('admin.models.destroy', $model) }}" onsubmit="return confirm('Hapus model {{ $model->public_name }}? Semua pricing rule-nya ikut terhapus.')">@csrf @method('delete')<button class="danger">Hapus</button></form></div></td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--muted)">Belum ada model — tambahkan di atas.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection
