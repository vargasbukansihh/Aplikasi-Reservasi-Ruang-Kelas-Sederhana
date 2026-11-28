# Aplikasi-Reservasi-Kelas-Sederhana

## Menjalankan aplikasi

1. Jalankan Apache melalui Laragon.
2. Buka `http://localhost/Aplikasi-Reservasi-Kelas-Sederhana/login.php`.

Halaman menggunakan PHP untuk penyajian, dengan CSS dan JavaScript lokal di folder `assets`. Antarmuka tidak lagi memuat Bootstrap.

## Email akun dan reset password

Registrasi mahasiswa mewajibkan email unik. Email akun admin yang sudah ada dapat diisi melalui menu **Data Pengguna**.

Konfigurasi SMTP disimpan secara lokal di `db/smtp_config.php`. Jika belum ada, salin `db/smtp_config.example.php` menjadi `db/smtp_config.php`, lalu isi host, port, username, password, jenis enkripsi, alamat pengirim, dan URL aplikasi. File konfigurasi lokal diabaikan Git dan dilindungi dari akses HTTP langsung.

Pengiriman menggunakan SMTP langsung dari PHP dan tidak memerlukan Composer atau folder `vendor`. Gunakan kredensial SMTP atau app password dari penyedia email; jangan masukkan password akun email utama dan jangan hapus aturan `.gitignore` untuk file konfigurasi lokal. Umumnya port 587 menggunakan `tls`, sedangkan port 465 menggunakan `ssl`. Di luar localhost, URL aplikasi wajib menggunakan HTTPS.

Tautan reset hanya berlaku selama 30 menit dan hanya dapat digunakan sekali.