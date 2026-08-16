@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Kode Berhasil Dibuat</h2><p>Simpan sekarang. Plaintext tidak dapat ditampilkan kembali.</p></div><a class="btn secondary" href="{{ route('admin.redeem-codes.index') }}">Kembali</a></div>
<div class="card"><h3>{{ $batch->label ?: 'Batch #'.$batch->id }}</h3><button type="button" onclick="downloadCodes()">Download TXT</button><pre class="key" id="codes" style="margin-top:14px">{{ implode("\n", $codes) }}</pre></div>
<script>function downloadCodes(){var blob=new Blob([document.getElementById('codes').textContent+'\n'],{type:'text/plain'});var link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='redeem-codes-{{ $batch->id }}.txt';link.click();URL.revokeObjectURL(link.href)}</script>
@endsection
