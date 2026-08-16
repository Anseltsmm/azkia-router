<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi · Azkia Router</title>
    <meta name="description" content="Kebijakan Privasi layanan Azkia Router.">
    <style>
        :root{--bg:#f8fafc;--panel:#fff;--ink:#0f172a;--body:#334155;--muted:#64748b;--line:#e2e8f0;--brand:#2563eb}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--body);font:15px/1.75 Inter,ui-sans-serif,system-ui,sans-serif}.legal-footer{border-top:1px solid var(--line);background:var(--panel);color:var(--muted);font-size:13px}.legal-footer-inner{max-width:900px;margin:auto;padding:22px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.legal-footer nav{display:flex;gap:16px;flex-wrap:wrap}.legal-footer a{font-weight:600;text-decoration:none}.legal-nav{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px;max-width:900px;margin:auto;padding:0 24px;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;color:var(--ink);font-weight:800;text-decoration:none}.brand img{width:34px;height:34px;object-fit:contain}.back{color:var(--brand);font-weight:700;text-decoration:none;font-size:13px}.legal{max-width:800px;margin:40px auto 70px;padding:0 24px}.legal-card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:34px;box-shadow:0 4px 18px rgba(15,23,42,.05)}h1,h2{color:var(--ink);letter-spacing:-.02em}h1{font-size:30px;margin:0 0 6px}h2{font-size:18px;margin:28px 0 8px}.updated{color:var(--muted);font-size:13px;margin-bottom:24px}p,li{margin-top:0}ul{padding-left:20px}a{color:var(--brand)}@media(max-width:600px){.legal-card{padding:23px}.legal{margin-top:24px}h1{font-size:25px}}
    </style>
</head>
<body>
<nav class="legal-nav"><a class="brand" href="/"><img src="{{ asset('azkia-logo.png') }}" alt="Logo Azkia Router">Azkia Router</a><a class="back" href="/">Kembali ke Beranda</a></nav>
<main class="legal"><article class="legal-card">
    <h1>Kebijakan Privasi</h1>
    <div class="updated">Terakhir diperbarui: {{ date('d F Y') }}</div>
    <p>Kebijakan Privasi ini menjelaskan bagaimana Azkia Router mengumpulkan, menggunakan, menyimpan, dan melindungi informasi pengguna saat menggunakan situs, dashboard, layanan pembayaran, dan AI API Gateway kami.</p>

    <h2>1. Informasi yang Kami Kumpulkan</h2>
    <ul>
        <li>Data akun seperti nama, alamat email, kata sandi terenkripsi, dan status akun.</li>
        <li>Data teknis seperti alamat IP, user-agent, endpoint, model, jumlah token, latensi, kode status, dan waktu permintaan.</li>
        <li>Data transaksi seperti nominal top-up, metode pembayaran, referensi transaksi, status pembayaran, saldo, dan riwayat pemakaian.</li>
        <li>Konten permintaan API yang diperlukan untuk meneruskan permintaan ke penyedia model. Kami tidak menggunakan konten tersebut untuk tujuan periklanan.</li>
    </ul>

    <h2>2. Penggunaan Informasi</h2>
    <p>Informasi digunakan untuk menyediakan dan mengamankan layanan, memvalidasi API key, menerapkan kuota dan rate limit, menghitung biaya, memproses pembayaran, menambahkan saldo, menampilkan laporan penggunaan, menangani dukungan, serta memenuhi kewajiban hukum.</p>

    <h2>3. Pemrosesan oleh Pihak Ketiga</h2>
    <p>Kami dapat membagikan data yang diperlukan kepada penyedia infrastruktur, penyedia model AI, dan Tripay sebagai payment gateway. Pemrosesan tersebut dibatasi untuk menjalankan layanan dan tunduk pada kebijakan masing-masing penyedia.</p>

    <h2>4. Pembayaran</h2>
    <p>Data pembayaran diproses oleh Tripay. Azkia Router tidak menyimpan PIN, kata sandi perbankan, atau detail rahasia instrumen pembayaran pengguna. Kami menyimpan referensi dan status transaksi untuk rekonsiliasi serta penambahan saldo.</p>

    <h2>5. Penyimpanan dan Keamanan</h2>
    <p>Kami menerapkan pengamanan yang wajar, termasuk hashing kata sandi dan API key, enkripsi kredensial layanan, kontrol akses, serta verifikasi signature callback pembayaran. Tidak ada sistem yang sepenuhnya bebas risiko, sehingga pengguna wajib menjaga keamanan akun dan API key.</p>

    <h2>6. Retensi Data</h2>
    <p>Data disimpan selama akun aktif atau selama diperlukan untuk operasional, keamanan, penyelesaian sengketa, audit, dan kewajiban hukum. Sebagian riwayat transaksi dan penggunaan dapat tetap disimpan setelah akun ditutup apabila diwajibkan atau diperlukan secara sah.</p>

    <h2>7. Hak Pengguna</h2>
    <p>Pengguna dapat meminta pembaruan data akun, informasi mengenai data yang tersimpan, atau penghapusan akun, dengan memperhatikan kewajiban retensi hukum dan kebutuhan keamanan layanan.</p>

    <h2>8. Cookie dan Sesi</h2>
    <p>Kami menggunakan cookie atau penyimpanan sesi yang diperlukan untuk autentikasi, keamanan, preferensi tema, dan fungsi dashboard. Kami tidak menggunakan cookie tersebut untuk menjual data pribadi.</p>

    <h2>9. Perubahan Kebijakan</h2>
    <p>Kebijakan ini dapat diperbarui mengikuti perubahan layanan atau peraturan. Versi terbaru akan dipublikasikan pada halaman ini beserta tanggal pembaruannya.</p>

    <h2>10. Kontak</h2>
    <p>Pertanyaan atau permintaan terkait privasi dapat disampaikan melalui kanal dukungan resmi Azkia Router yang tersedia pada platform.</p>
</article></main>
@include('partials.legal-footer')
</body>
</html>
