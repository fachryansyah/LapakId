## Tugas Akhir Pemrograman Web 2
```bash
> Nama="Muhammad Fahriansyah"
> Nim="211011401531"
```

Source code ini tidak menggunakan framework PHP seperti Laravel / Symfony / Codeigniter. Melainkan, menggunakan PHP dasar dan library PHP pendukung seperti Twig, Phroute, dan Tailwind CSS untuk membangun aplikasi web dengan arsitektur MVC (Model-View-Controller).

Detailnya sebagai berikut:
- PHP v8.2: Digunakan untuk aplikasi web.
- MySQL v8.0: Digunakan untuk menyimpan data.
- Twig v3.4: Digunakan untuk render tampilan.
- Phroute v1.1: Digunakan untuk routing.
- Tailwind CSS v3.4: Digunakan untuk styling.
- Docker v24.06.1: Digunakan untuk menjalankan aplikasi di container.
- Docker Compose v2.14.2: Digunakan untuk menjalankan aplikasi di container dengan mudah.


# LapakId

LapakId adalah aplikasi E-commerce web top up berbasis PHP untuk menampilkan katalog produk digital seperti game, pulsa, token PLN, dan paket data. Proyek ini menyediakan halaman publik untuk melihat produk serta panel admin untuk mengelola produk dan memantau transaksi.

## Fitur Utama

- Halaman publik untuk beranda, daftar produk, pencarian produk, filter produk, dan detail produk.
- Panel admin untuk login, dashboard ringkasan, manajemen produk, dan daftar transaksi.
- Dukungan produk dengan banyak item/paket harga dalam satu entri.
- Upload media produk untuk ikon dan cover.
- Render tampilan menggunakan Twig.
- Styling menggunakan Tailwind CSS.

## Teknologi yang Digunakan

- PHP
- MySQL
- Twig
- Phroute
- Tailwind CSS
- Docker dan Docker Compose

## Struktur Direktori

```text
.
|-- bootstrap/        # Bootstrap aplikasi
|-- db/               # Skema database awal
|-- docker/           # Konfigurasi container
|-- public/           # Entry point web dan aset publik
|-- routes/           # Definisi routing
|-- src/              # Controller dan core aplikasi
|-- views/            # Template Twig
|-- composer.json     # Dependency PHP
|-- package.json      # Dependency frontend
|-- docker-compose.yml
`-- makefile
```

## Persiapan

Pastikan perangkat Anda sudah memiliki:

- PHP 8.x
- Composer
- Node.js dan npm
- MySQL
- Docker dan Docker Compose (opsional, jika ingin menjalankan via container)

## Konfigurasi Environment

1. Salin file environment:

```bash
cp .env.example .env
```

2. Sesuaikan isi `.env` sesuai koneksi database Anda:

```env
APP_NAME=LapakId
APP_ENV=production
APP_DEBUG=false
APP_PORT=8000

DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=lapakid
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

Catatan:

- Jika database berjalan di host saat aplikasi dijalankan lewat Docker Desktop, gunakan `DB_HOST=host.docker.internal`.
- Jika aplikasi dijalankan langsung di mesin lokal tanpa Docker, biasanya `DB_HOST=127.0.0.1` atau `localhost`.

## Instalasi Lokal

1. Pasang dependency PHP:

```bash
composer install
```

2. Pasang dependency frontend:

```bash
npm install
```

3. Import skema database:

```bash
mysql -u root -p lapakid < db/lapakid_schema.sql
```

4. Build file CSS:

```bash
npm run build:css
```

5. Jalankan server development:

```bash
make dev
```

6. Buka aplikasi di browser:

```text
http://127.0.0.1:8000
```

## Menjalankan Dengan Docker

1. Pastikan file `.env` sudah benar.
2. Jalankan container:

```bash
docker compose up --build
```

3. Buka aplikasi:

```text
http://localhost:8000
```

Catatan:

- Container aplikasi memetakan port `${APP_PORT}` ke port `80` di dalam container.
- Volume `app_storage` digunakan untuk menyimpan file upload pada `public/storage`.

## Build CSS

Perintah yang tersedia:

```bash
npm run build:css
```

Untuk mode watch saat pengembangan:

```bash
npm run dev:css
```

## Database Awal

File `db/lapakid_schema.sql` akan membuat tabel:

- `users`
- `products`
- `product_items`
- `transactions`

Skema ini juga menambahkan akun admin default untuk akses pertama:

- Email: `admin@lapakid.test`
- Password: `admin12345`

Segera ubah kredensial default jika aplikasi digunakan di luar lingkungan lokal.

## Routing Utama

Halaman publik:

- `/`
- `/products`
- `/products/{id}`

Panel admin:

- `/admin/login`
- `/admin`
- `/admin/products`
- `/admin/transactions`

## Alur Pengembangan

Rekomendasi alur kerja lokal:

1. Jalankan MySQL.
2. Import `db/lapakid_schema.sql`.
3. Jalankan `composer install`.
4. Jalankan `npm install`.
5. Jalankan `npm run dev:css` saat mengubah tampilan.
6. Jalankan `make dev` untuk server PHP.

## Catatan Tambahan

- Aplikasi memakai soft delete pada beberapa data seperti produk dan item produk.
- Koneksi database menggunakan PDO dengan prepared statement.
- Jika koneksi database gagal, periksa kembali isi `.env`, terutama `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.

## Penulis

- Muhammad Fahriansyah
