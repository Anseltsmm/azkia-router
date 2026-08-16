<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Syarat & Ketentuan · Azkia Router</title>
    <meta name="description" content="Syarat dan Ketentuan layanan Azkia Router.">
    <style>
        :root{--bg:#f8fafc;--panel:#fff;--ink:#0f172a;--body:#334155;--muted:#64748b;--line:#e2e8f0;--brand:#2563eb}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--body);font:15px/1.75 Inter,ui-sans-serif,system-ui,sans-serif}.legal-footer{border-top:1px solid var(--line);background:var(--panel);color:var(--muted);font-size:13px}.legal-footer-inner{max-width:900px;margin:auto;padding:22px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.legal-footer nav{display:flex;gap:16px;flex-wrap:wrap}.legal-footer a{font-weight:600;text-decoration:none}.legal-nav{height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px;max-width:900px;margin:auto;padding:0 24px;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;color:var(--ink);font-weight:800;text-decoration:none}.brand img{width:34px;height:34px;object-fit:contain}.back{color:var(--brand);font-weight:700;text-decoration:none;font-size:13px}.legal{max-width:800px;margin:40px auto 70px;padding:0 24px}.legal-card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:34px;box-shadow:0 4px 18px rgba(15,23,42,.05)}h1,h2{color:var(--ink);letter-spacing:-.02em}h1{font-size:30px;margin:0 0 6px}h2{font-size:18px;margin:28px 0 8px}.updated{color:var(--muted);font-size:13px;margin-bottom:24px}p,li{margin-top:0}ul{padding-left:20px}a{color:var(--brand)}@media(max-width:600px){.legal-card{padding:23px}.legal{margin-top:24px}h1{font-size:25px}}
    </style>
</head>
<body>
<nav class="legal-nav"><a class="brand" href="/"><img src="{{ asset('azkia-logo.png') }}" alt="Logo Azkia Router">Azkia Router</a><a class="back" href="/">Kembali ke Beranda</a></nav>
<main class="legal"><article class="legal-card">
    <h1>Syarat & Ketentuan</h1>
    <div class="updated">Terakhir diperbarui: {{ date('d F Y') }}</div>
    <p>Dengan mendaftar atau menggunakan Azkia Router, pengguna menyatakan telah membaca, memahami, dan menyetujui Syarat & Ketentuan berikut.</p>

    <h2>1. Ruang Lingkup Layanan</h2>
    <p>Azkia Router menyediakan layanan digital berupa AI API Gateway yang kompatibel dengan OpenAI untuk mengakses berbagai model AI melalui satu API key, termasuk fitur saldo, billing berdasarkan pemakaian, monitoring, dan pengelolaan akun.</p>

    <h2>2. Akun Pengguna</h2>
    <ul>
        <li>Pengguna wajib memberikan informasi akun yang benar dan menjaga keamanan kata sandi serta API key.</li>
        <li>Aktivitas melalui akun dan API key dianggap dilakukan oleh pemilik akun, kecuali telah dilaporkan sebagai akses tidak sah.</li>
        <li>Akun tidak boleh dipindahtangankan, dijual, atau digunakan untuk menyamarkan identitas pelaku lain.</li>
    </ul>

    <h2>3. Penggunaan yang Diperbolehkan</h2>
    <p>Pengguna wajib mematuhi hukum Indonesia, kebijakan penyedia model, dan ketentuan teknis platform. Layanan tidak boleh digunakan untuk tindakan ilegal, penipuan, eksploitasi, serangan siber, penyebaran malware, pelanggaran hak pihak lain, spam, atau upaya mengganggu infrastruktur.</p>

    <h2>4. API Key, Kuota, dan Rate Limit</h2>
    <p>Setiap API key dapat memiliki batas permintaan, kuota token, masa berlaku, dan status aktif. Kami dapat membatasi atau menonaktifkan key untuk menjaga keamanan, stabilitas, mencegah penyalahgunaan, atau memenuhi kewajiban hukum.</p>

    <h2>5. Harga dan Perhitungan Pemakaian</h2>
    <p>Layanan menggunakan sistem pay-as-you-go. Biaya dihitung berdasarkan model, jumlah token input/output, cache, serta komponen lain yang ditampilkan pada dashboard. Harga dapat berubah dan harga terbaru yang tersedia pada platform berlaku untuk permintaan berikutnya.</p>

    <h2>6. Top Up dan Pembayaran</h2>
    <ul>
        <li>Top-up diproses melalui Tripay dan metode pembayaran yang tersedia dapat berubah.</li>
        <li>Saldo ditambahkan setelah pembayaran berstatus berhasil dan callback tervalidasi.</li>
        <li>Biaya administrasi channel pembayaran dapat dibebankan sesuai informasi saat transaksi.</li>
        <li>Transaksi yang belum dibayar hingga masa berlaku berakhir dapat berstatus kedaluwarsa.</li>
    </ul>

    <h2>7. Saldo dan Pengembalian Dana</h2>
    <p>Saldo merupakan kredit layanan digital yang hanya dapat digunakan untuk mengakses layanan di platform Azkia Router. Saldo bukan rekening bank, uang elektronik, instrumen investasi, atau alat pembayaran di luar platform. Setiap saldo yang telah berhasil ditambahkan ke akun tidak dapat diuangkan, ditarik, dipindahkan ke akun lain, atau dikembalikan, baik seluruhnya maupun sebagian. Pengguna bertanggung jawab memastikan nominal top-up sebelum menyelesaikan pembayaran.</p>

    <h2>8. Ketersediaan Model dan Layanan</h2>
    <p>Model, provider, harga, kemampuan, context window, dan ketersediaan dapat berubah. Kami berupaya menjaga layanan tetap tersedia, namun tidak menjamin layanan bebas gangguan, latensi, perubahan keluaran model, atau penghentian model oleh penyedia.</p>

    <h2>9. Konten dan Keluaran AI</h2>
    <p>Pengguna bertanggung jawab atas konten yang dikirim serta penggunaan keluaran AI. Keluaran model dapat tidak akurat, tidak lengkap, atau tidak sesuai. Pengguna wajib melakukan verifikasi manusia sebelum menggunakannya untuk keputusan penting, termasuk keputusan hukum, medis, keuangan, atau keselamatan.</p>

    <h2>10. Penangguhan dan Penghentian</h2>
    <p>Kami dapat menangguhkan atau menghentikan akun yang melanggar ketentuan, menimbulkan risiko keamanan, gagal memenuhi kewajiban pembayaran, atau diwajibkan oleh hukum. Jika memungkinkan, pemberitahuan akan diberikan melalui dashboard, inbox, atau email.</p>

    <h2>11. Batasan Tanggung Jawab</h2>
    <p>Sejauh diizinkan hukum, Azkia Router tidak bertanggung jawab atas kerugian tidak langsung, kehilangan data, kehilangan keuntungan, gangguan pihak ketiga, atau keputusan yang dibuat berdasarkan keluaran AI. Tanggung jawab langsung, jika ada, dibatasi pada jumlah yang dibayarkan pengguna untuk layanan dalam periode terkait.</p>

    <h2>12. Perubahan Ketentuan</h2>
    <p>Kami dapat memperbarui ketentuan ini seiring perubahan layanan, provider, sistem pembayaran, atau peraturan. Penggunaan layanan setelah pembaruan berarti pengguna menyetujui versi terbaru.</p>

    <h2>13. Hukum yang Berlaku</h2>
    <p>Ketentuan ini tunduk pada hukum Republik Indonesia. Perselisihan akan diupayakan selesai melalui musyawarah sebelum menempuh mekanisme hukum yang berlaku.</p>

    <h2>14. Kontak</h2>
    <p>Pertanyaan mengenai Syarat & Ketentuan dapat disampaikan melalui kanal dukungan resmi Azkia Router yang tersedia pada platform.</p>
</article></main>
@include('partials.legal-footer')
</body>
</html>
