# 🍛 Rumah Makan Padang Harapan Bundo - Web Ordering & Management System

Sistem aplikasi web restoran modern untuk **Rumah Makan Padang Harapan Bundo**, dirancang dengan standar kualitas enterprise: tampilan mobile-first mewah (mengadaptasi estetika layout modern referensi), responsif di smartphone & desktop, keamanan tingkat tinggi (*zero-vulnerability* dengan PDO Prepared Statements, CSRF Protection, Password Hashing, XSS Filter), integrasi pembayaran **QRIS Dinamis (Standar ASPI / Bank Indonesia)** serta **DOKU Payment Gateway**, live status order tracking, dan panel manajemen admin komprehensif.

---

## 🌟 Fitur Utama

### 📱 1. Antarmuka Pelanggan (Customer Web App)
- **Tampilan Modern Mobile & Desktop**: Desain elegan bernuansa Minangkabau modern (Burgundy Crimson, Warm Gold, Cream).
- **Mode Layanan Hybrid**:
  - **Makan di Tempat (Dine-in)**: Pilih / input nomor meja (Meja 01 - 30).
  - **Antar ke Alamat (Delivery)**: Masukkan alamat lengkap, patokan, dan ongkir otomatis.
- **Kategori Filter Interaktif**:
  - Lauk Daging (Rendang Suntiang, Dendeng Batokok, Paru)
  - Ayam & Bebek (Ayam Pop Bukittinggi, Ayam Bakar Padang)
  - Seafood & Gulai (Gulai Kepala Kakap, Gulai Tunjang Kikil)
  - Sayur & Sambal (Sambalado Ijo, Sayur Kapau Nangka)
  - Paket Nasi Lengkap
  - Minuman Khas (Teh Talua, Es Tebak)
- **Kustomisasi Santapan**: Pop-up dialog untuk atur jumlah porsi dan catatan khusus dapur (*"Kuah gulai banyakan da"*, *"Sambal ijo dipisah"*).
- **Floating Bottom Navigation & Cart**: Tombol keranjang melayang dengan indikator badge pesanan dan total harga realtime.
- **Pencarian Cepat**: Filter pencarian instan nama menu dan deskripsi rempah.
- **Program Loyalitas**: Banner *Harapan Bundo Rewards*.

### 💳 2. Sistem Pembayaran QRIS & Gateway DOKU
- **QRIS Dinamis Otomatis**:
  - Menghasilkan payload QRIS resmi standar EMVCo / Bank Indonesia dengan NMID dan Merchant Name resmi.
  - Dilengkapi perhitungan CRC16-CCITT dan timer hitung mundur pembayaran 15 menit.
  - **Mode Uji Coba Demo (1-Click Simulation)**: Tersedia tombol simulasi pembayaran instan untuk pengujian sandbox tanpa harus transfer nyata.
- **DOKU Payment Gateway Ready**:
  - Konfigurasi Client ID & Shared Key di menu pengaturan admin.
  - Endpoint Webhook (`api/doku_notify.php`) dengan pencatatan `payment_logs` dan verifikasi status transaksi.

### ⏱️ 3. Pelacakan Pesanan Realtime (Live Order Tracking)
- Halaman digital receipt dan timeline progres dapur:
  1. *Pesanan Dibuat*
  2. *Pembayaran Terkonfirmasi*
  3. *Sedang Diracik di Dapur Bundo*
  4. *Siap Disajikan ke Meja / Diantar Kurir*
  5. *Pesanan Selesai*
- Fitur **Auto-Polling Realtime**: Status pada layar pelanggan otomatis berganti saat koki/admin mengubah status di dapur.
- Tombol integrasi WhatsApp untuk menghubungi pihak restoran secara instan.

### 🛡️ 4. Panel Manajemen Resto (Admin Dashboard)
- **Akses Keamanan**:
  - Login terlindungi CSRF token dan password hashing `PASSWORD_BCRYPT`.
  - Kredensial default:
    - **Username**: `admin`
    - **Password**: `admin123`
- **Dashboard Operasional**:
  - Metrik pendapatan hari ini, jumlah pesanan, dan pesanan aktif.
  - **Audio Alert System**: Bunyi bel notifikasi otomatis saat ada pesanan baru masuk ke dapur.
- **Manajemen Pesanan**:
  - Filter pesanan berdasarkan status dan layanan (Dine-in / Delivery).
  - Update status pesanan langsung dari tabel.
  - **Cetak Struk Kasir Thermal (58mm / 80mm)**: Format struk kasir profesional siap cetak.
- **Manajemen Menu & Harga (CRUD)**:
  - Tambah menu baru dengan upload foto atau URL Google Images.
  - Atur harga normal dan harga promo/diskon.
  - Pilihan level pedas (1 s/d 3 cabai).
  - **Toggle Stok 1-Klik**: Tombol instan ubah status menu *"Tersedia"* / *"Habis"*.
- **Pengaturan Resto & DOKU**:
  - Konfigurasi profil restoran, nomor WhatsApp kasir, biaya ongkir flat, pajak resto.
  - Pengaturan mode DOKU (Sandbox / Production), Mall ID, Shared Key, dan NMID QRIS.

---

## 📁 Struktur Direktori

```text
restorant/
├── index.php             # Halaman utama aplikasi pemesanan customer
├── track.php             # Halaman live status pelacakan pesanan & invoice digital
├── config.php            # Koneksi PDO, Helper keamanan, Generator QRIS EMVCo & DOKU
├── database.sql          # Skema database MySQL + data awal menu Padang & akun admin
├── api/
│   ├── menu.php          # Public REST API daftar menu & kategori
│   ├── order.php         # Public REST API pembuatan pesanan & validasi harga
│   ├── payment.php       # Public REST API cek status pembayaran & simulasi QRIS
│   ├── doku_notify.php   # Webhook callback listener DOKU payment gateway
│   └── order_status.php  # Public REST API live poller status pesanan
├── admin/
│   ├── index.php         # Dashboard utama admin, monitoring & live orders
│   ├── login.php         # Halaman login admin dengan proteksi CSRF
│   ├── logout.php        # Script logout aman
│   ├── orders.php        # Kelola seluruh arsip pesanan & cetak struk kasir
│   ├── menu.php          # Kelola menu, harga promo, stok & upload gambar
│   ├── settings.php      # Pengaturan resto, ongkir & kredensial DOKU
│   └── api/
│       ├── orders.php    # Admin AJAX untuk update status & audio alert poller
│       └── menu.php      # Admin AJAX untuk CRUD menu & toggle stok
└── assets/
    ├── css/
    │   ├── app.css       # Styling aplikasi pemesanan pelanggan
    │   └── admin.css     # Styling dashboard admin modern
    ├── js/
    │   └── app.js        # Logika frontend, keranjang belanja & polling pembayaran
    └── images/           # Direktori penyimpanan upload foto hidangan
```

---

## 🚀 Cara Menjalankan di XAMPP

1. **Jalankan Apache & MySQL**:
   - Buka **XAMPP Control Panel**.
   - Klik **Start** pada modul **Apache** dan **MySQL**.

2. **Database Setup via phpMyAdmin**:
   - Buka browser dan akses: `http://localhost/phpmyadmin/`
   - Buat database baru bernama `harapan_bundo_db` (atau langsung import file).
   - Klik tab **Import**, pilih file [database.sql](file:///c:/Users/X1%20YOGA/Music/restorant/database.sql), lalu klik **Go** / **Kirim**.
   - Seluruh tabel, kategori, menu masakan Padang otentik, dan akun admin akan langsung terisi.

3. **Akses Aplikasi**:
   - **Tampilan Pelanggan (Customer Web App)**:
     `http://localhost/restorant/` (atau `http://localhost:8000/` jika menggunakan PHP CLI Server)
   - **Tampilan Manajemen Kasir & Admin**:
     `http://localhost/restorant/admin/login.php`
     - **Username**: `admin`
     - **Password**: `admin123`

---

## 🔒 Catatan Keamanan (Security Hardening)
- **Anti-SQL Injection**: Menggunakan PDO prepared statements dengan parameter binding pada 100% query database.
- **Anti-Client Price Tampering**: Seluruh perhitungan subtotal dan diskon diverifikasi ulang langsung dari database saat pesanan dibuat (mencegah manipulasi harga lewat browser inspector).
- **Anti-XSS**: Seluruh output data pelanggan dan input teks di-escape menggunakan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Anti-CSRF**: Form sensitif dilengkapi token anti-pemalsuan permintaan antar situs (`hash_equals`).
- **Session Hardening**: Menggunakan flag `HttpOnly`, `SameSite=Lax`, dan `session_regenerate_id()` untuk mencegah pembajakan sesi.
