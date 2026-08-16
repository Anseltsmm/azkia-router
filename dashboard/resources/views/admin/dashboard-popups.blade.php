@extends('layouts.app')

@section('content')
<style>
    .popup-layout{display:grid;grid-template-columns:minmax(300px,420px) minmax(0,1fr);gap:16px;align-items:start}
    .rich-toolbar{display:flex;align-items:center;gap:5px;flex-wrap:wrap;padding:7px;background:var(--soft);border:1px solid var(--line);border-bottom:0;border-radius:9px 9px 0 0}
    .rich-toolbar button,.rich-toolbar select,.rich-toolbar input{width:auto;height:30px;margin:0;padding:4px 8px;font-size:12px}
    .rich-toolbar input[type=color]{width:34px;padding:3px}
    .rich-editor{min-height:140px;border:1px solid var(--line);background:var(--input);color:var(--ink);border-radius:0 0 9px 9px;padding:11px 12px;margin-bottom:10px;overflow-wrap:anywhere}
    .rich-editor:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.14)}
    .rich-editor p{margin:0 0 8px}.rich-editor ul,.rich-editor ol{padding-left:22px}
    .popup-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .popup-title{font-weight:750;color:var(--ink)}
    .popup-body{margin:5px 0;color:var(--body);white-space:pre-line}
    .popup-shine,.popup-shine-all,.popup-shine-all *{background:linear-gradient(90deg,#2563eb,#0ea5e9,#ffffff,#0ea5e9,#2563eb);background-size:240% 100%;-webkit-background-clip:text;background-clip:text;color:transparent!important;animation:popupShine 2.8s linear infinite;font-weight:800}
    @keyframes popupShine{from{background-position:200% 0}to{background-position:-200% 0}}
    @media(prefers-reduced-motion:reduce){.popup-shine,.popup-shine-all,.popup-shine-all *{animation:none;background-position:50% 0}}
    .popup-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}
    .popup-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
    details.popup-edit{margin-top:10px;border-top:1px solid var(--line);padding-top:10px}
    details.popup-edit summary{cursor:pointer;color:var(--brand);font-weight:700;font-size:12.5px}
    @media(max-width:899.98px){.popup-layout{grid-template-columns:1fr}}
</style>

<div class="top"><div><h2>Popup Dashboard</h2><p>Buat promo atau pengumuman yang tampil pada dashboard user.</p></div><span class="pill">{{ $popups->total() }} popup</span></div>
@include('admin._alerts')

<div class="popup-layout">
    <div class="card popup-form">
        <h3>Buat Popup</h3>
        <form method="post" action="{{ route('admin.dashboard-popups.store') }}">@csrf
            <label class="muted">Judul</label>
            <input name="title" value="{{ old('title') }}" maxlength="255" required>
            <label class="muted">Isi</label>
            <div class="rich-wrap">
                <div class="rich-toolbar">
                    <button class="secondary" type="button" data-command="bold"><strong>B</strong></button><button class="secondary" type="button" data-command="italic"><em>I</em></button><button class="secondary" type="button" data-command="underline"><u>U</u></button>
                    <button class="secondary" type="button" data-command="insertUnorderedList">• List</button><button class="secondary" type="button" data-command="insertOrderedList">1. List</button>
                    <select data-format><option value="">Ukuran</option><option value="2">Kecil</option><option value="3">Normal</option><option value="5">Besar</option><option value="6">Judul</option></select>
                    <input type="color" data-color value="#2563eb" title="Warna teks">
                    <select data-align><option value="">Rata teks</option><option value="justifyLeft">Kiri</option><option value="justifyCenter">Tengah</option><option value="justifyRight">Kanan</option></select>
                    <button class="secondary" type="button" data-link>Tautan</button><label style="display:inline-flex;align-items:center;gap:5px;margin:0;padding:4px 8px;font-size:12px"><input type="checkbox" data-shine-mode style="width:15px;height:15px;margin:0"> Efek Kilap</label><button class="secondary" type="button" data-command="removeFormat">Hapus gaya</button>
                </div>
                <div class="rich-editor" contenteditable="true">{!! old('body') !!}</div>
                <textarea name="body" hidden required>{{ old('body') }}</textarea>
            </div>
            <div class="form-row">
                <div><label class="muted">Jenis</label><select name="type"><option value="info">Informasi</option><option value="promo">Promo</option><option value="success">Sukses</option><option value="warning">Peringatan</option></select></div>
                <div><label class="muted">Teks Tombol</label><input name="button_text" value="{{ old('button_text') }}" placeholder="Lihat promo"></div>
            </div>
            <label class="muted">URL Tombol</label>
            <input name="button_url" type="url" value="{{ old('button_url') }}" placeholder="https://azkia.cloud/models">
            <div class="form-row">
                <div><label class="muted">Mulai (WIB)</label><input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}"></div>
                <div><label class="muted">Berakhir (WIB)</label><input name="ends_at" type="datetime-local" value="{{ old('ends_at') }}"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin:4px 0 16px"><input name="is_active" type="checkbox" value="1" @checked(old('is_active')) style="width:16px;height:16px;margin:0"> Langsung aktifkan</label>
            <button type="submit">Buat Popup</button>
        </form>
    </div>

    <div style="display:grid;gap:10px">
        @forelse($popups as $popup)
        <div class="card">
            <div class="popup-row"><span class="popup-title">{{ $popup->title }}</span><span class="badge {{ $popup->is_active ? 'green' : 'amber' }}">{{ $popup->is_active ? 'aktif' : 'nonaktif' }}</span></div>
            <div class="popup-body">{!! $popup->body !!}</div>
            <div class="popup-meta">
                <span class="badge">{{ $popup->type }}</span>
                @if($popup->starts_at)<span class="badge">Mulai {{ $popup->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>@endif
                @if($popup->ends_at)<span class="badge">Selesai {{ $popup->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>@endif
            </div>
            <div class="popup-actions">
                <form method="post" action="{{ route('admin.dashboard-popups.toggle', $popup) }}">@csrf @method('patch')<button class="secondary" type="submit">{{ $popup->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                <form method="post" action="{{ route('admin.dashboard-popups.destroy', $popup) }}" onsubmit="return confirm('Hapus popup ini?')">@csrf @method('delete')<button class="danger" type="submit">Hapus</button></form>
            </div>
            <details class="popup-edit">
                <summary>Edit popup</summary>
                <form method="post" action="{{ route('admin.dashboard-popups.update', $popup) }}" style="margin-top:10px">@csrf @method('patch')
                    <label class="muted">Judul</label><input name="title" value="{{ $popup->title }}" required>
                    <label class="muted">Isi</label>
                    <div class="rich-wrap">
                        <div class="rich-toolbar">
                            <button class="secondary" type="button" data-command="bold"><strong>B</strong></button><button class="secondary" type="button" data-command="italic"><em>I</em></button><button class="secondary" type="button" data-command="underline"><u>U</u></button>
                            <button class="secondary" type="button" data-command="insertUnorderedList">• List</button><button class="secondary" type="button" data-command="insertOrderedList">1. List</button>
                            <select data-format><option value="">Ukuran</option><option value="2">Kecil</option><option value="3">Normal</option><option value="5">Besar</option><option value="6">Judul</option></select>
                            <input type="color" data-color value="#2563eb" title="Warna teks">
                            <select data-align><option value="">Rata teks</option><option value="justifyLeft">Kiri</option><option value="justifyCenter">Tengah</option><option value="justifyRight">Kanan</option></select>
                            <button class="secondary" type="button" data-link>Tautan</button><label style="display:inline-flex;align-items:center;gap:5px;margin:0;padding:4px 8px;font-size:12px"><input type="checkbox" data-shine-mode style="width:15px;height:15px;margin:0"> Efek Kilap</label><button class="secondary" type="button" data-command="removeFormat">Hapus gaya</button>
                        </div>
                        <div class="rich-editor" contenteditable="true">{!! $popup->body !!}</div>
                        <textarea name="body" hidden required>{{ $popup->body }}</textarea>
                    </div>
                    <div class="form-row">
                        <div><label class="muted">Jenis</label><select name="type">@foreach(['info' => 'Informasi', 'promo' => 'Promo', 'success' => 'Sukses', 'warning' => 'Peringatan'] as $value => $label)<option value="{{ $value }}" @selected($popup->type === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="muted">Teks Tombol</label><input name="button_text" value="{{ $popup->button_text }}"></div>
                    </div>
                    <label class="muted">URL Tombol</label><input name="button_url" type="url" value="{{ $popup->button_url }}">
                    <div class="form-row">
                        <div><label class="muted">Mulai (WIB)</label><input name="starts_at" type="datetime-local" value="{{ $popup->starts_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}"></div>
                        <div><label class="muted">Berakhir (WIB)</label><input name="ends_at" type="datetime-local" value="{{ $popup->ends_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}"></div>
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;margin:4px 0 16px"><input name="is_active" type="checkbox" value="1" @checked($popup->is_active) style="width:16px;height:16px;margin:0"> Aktif</label>
                    <button type="submit">Simpan Perubahan</button>
                </form>
            </details>
        </div>
        @empty
        <div class="card"><p class="muted" style="margin:0">Belum ada popup.</p></div>
        @endforelse
        {{ $popups->links() }}
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('.rich-wrap').forEach(function (wrap) {
        var editor = wrap.querySelector('.rich-editor');
        var textarea = wrap.querySelector('textarea[name="body"]');
        var toolbar = wrap.querySelector('.rich-toolbar');

        var savedRange = null;

        function saveSelection() {
            var selection = window.getSelection();
            if (selection && selection.rangeCount && editor.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
        }
        function restoreSelection() {
            editor.focus();
            if (!savedRange) return;
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        }
        function command(name, value) {
            restoreSelection();
            document.execCommand('styleWithCSS', false, true);
            document.execCommand(name, false, value || null);
            saveSelection();
        }

        var shineMode = toolbar.querySelector('[data-shine-mode]');

        shineMode.addEventListener('change', function () {
            if (shineMode.checked || !savedRange) return;
            var container = savedRange.startContainer.nodeType === Node.TEXT_NODE ? savedRange.startContainer.parentElement : savedRange.startContainer;
            var span = container && container.closest ? container.closest('.popup-shine') : null;
            if (!span || !editor.contains(span)) return;
            var range = document.createRange();
            range.setStartAfter(span);
            range.collapse(true);
            savedRange = range;
        });

        editor.addEventListener('beforeinput', function (event) {
            if (!shineMode.checked || event.inputType !== 'insertText' || !event.data) return;
            event.preventDefault();
            var selection = window.getSelection();
            if (!selection || !selection.rangeCount) return;
            var range = selection.getRangeAt(0);
            range.deleteContents();
            var current = range.startContainer.nodeType === Node.TEXT_NODE ? range.startContainer.parentElement : range.startContainer;
            var span = current && current.closest ? current.closest('.popup-shine') : null;
            var text = document.createTextNode(event.data);
            if (!span || !editor.contains(span)) {
                span = document.createElement('span');
                span.className = 'popup-shine';
                span.appendChild(text);
                range.insertNode(span);
            } else {
                range.insertNode(text);
            }
            range.setStartAfter(text);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            savedRange = range.cloneRange();
        });
        editor.addEventListener('mouseup', saveSelection);
        editor.addEventListener('keyup', saveSelection);
        editor.addEventListener('input', saveSelection);
        toolbar.addEventListener('mousedown', function (event) {
            if (!event.target.closest('input[type="color"], select')) event.preventDefault();
            saveSelection();
        });
        toolbar.querySelectorAll('[data-command]').forEach(function (button) {
            button.addEventListener('click', function () { command(button.dataset.command); });
        });
        toolbar.querySelector('[data-format]').addEventListener('change', function () { if (this.value) command('fontSize', this.value); this.value = ''; });
        toolbar.querySelector('[data-color]').addEventListener('input', function () { command('foreColor', this.value); });
        toolbar.querySelector('[data-align]').addEventListener('change', function () { if (this.value) command(this.value); this.value = ''; });
        toolbar.querySelector('[data-link]').addEventListener('click', function () {
            restoreSelection();
            var url = window.prompt('Masukkan URL lengkap (https://...)');
            if (url && /^https?:\/\//i.test(url)) command('createLink', url);
        });
        editor.closest('form').addEventListener('submit', function () {
            textarea.value = editor.innerHTML.trim();
        });
    });
})();
</script>
@endsection
