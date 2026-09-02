# SIAKAD API (api-siakad-ver3)

Backend **REST API** untuk sistem SIAKAD modern. Berbasis **Laravel 11**, menyajikan seluruh proses akademik (master data, kurikulum/OBE, KRS, perkuliahan & penilaian, KHS/transkrip, tugas akhir, yudisium/kelulusan/wisuda) melalui HTTP.

> Bagian dari workspace **dev-siakad** yang berisi dua aplikasi independen: backend API ini (port **8001**) dan frontend Blade `siakad-ver3` (port **8000**). Kedua aplikasi dijalankan bersama namun dikembangkan terpisah.

## Ringkasan

| Aspek | Nilai |
|-------|-------|
| Framework | Laravel 11 (PHP) |
| Database | MySQL (`db_siakad`) |
| Port | 8001 (`php artisan serve`) |
| Timezone aplikasi | `Asia/Jakarta` (`config/app.php` `APP_TIMEZONE`) |
| Penyimpanan kolom datetime | **UTC** — wajib `Carbon::now('UTC')` saat tulis, konversi ke `Asia/Jakarta` saat tampil (keputusan D-20) |
| Auth | **JWT** (`tymon/jwt-auth`), guard `api`, middleware `jwt.token` |
| RBAC | `spatie/laravel-permission`, middleware `check.role.permission` (permission = nama route) |
| Prefix route | `/api/v1` di `routes/api.php` |
| Integrasi internal | Frontend memanggil via Guzzle + `internal_api` key/secret (`config/services.php` / `INTERNAL_API_*`) |

## Persyaratan

- PHP ≥ 8.2, Composer
- MySQL (untuk produksi; `.env.example` default sqlite hanya contoh)
- Ekstensi PHP yang dibutuhkan Laravel (openssl, pdo_mysql, mbstring, dll.)

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed        # (opsional, dev)
php artisan serve          # port 8001
```

> **Catatan `.env.example`:** file contoh masih memuat `APP_TIMEZONE=UTC` dan `DB_CONNECTION=sqlite` dari boilerplate. Sesuaikan dengan `.env` aktual yang memakai `APP_TIMEZONE=Asia/Jakarta` dan `DB_CONNECTION=mysql`.

## Konfigurasi `.env` (kunci)

| Variabel | Keterangan |
|----------|------------|
| `APP_TIMEZONE` | `Asia/Jakarta` |
| `DB_CONNECTION` | `mysql` (`db_siakad`), host `127.0.0.1` lokal |
| `JWT_SECRET` | rahasia JWT (`php artisan jwt:secret`) |
| `INTERNAL_API_KEY` / `INTERNAL_API_SECRET` | kredensial integrasi internal frontend |
| `QUEUE_CONNECTION` / `CACHE_STORE` | `database` (dev) → `redis` (produksi) |
| `PDDIKTI_*` | kredensial WebService PDDikti Feeder (jika digunakan) |

## Autentikasi

- Login: `POST /api/v1/auth/login` → `access_token` / `refresh_token` / `expires_in`.
- Protected route: `Authorization: Bearer <token>` + middleware `jwt.token`.
- RBAC: `check.role.permission` — permission = nama route; role `admin` lolos semua.

## Struktur Direktori (inti)

```
app/Http/Controllers/Api/   ← API controllers (tipis; logika di Service)
app/Services/               ← logika bisnis (Service Layer)
app/Http/Requests/          ← Form Request (validasi + authorize)
app/Models/                 ← Eloquent model (UUID string PK)
app/Jobs/                   ← job (queue) untuk operasi berat/sinkronisasi
routes/api.php              ← semua route di /api/v1
database/migrations/        ← migrasi (UUID keyed)
test.rest                   ← REST Client: contoh endpoint & body
```

## Menjalankan & Menguji

- Serve: `php artisan serve --port=8001`
- Format: `vendor/bin/pint`
- Test: `vendor/bin/phpunit`
- Cek route: `php artisan route:list`

## Catatan

- Kedua aplikasi memakai timezone `Asia/Jakarta`; kolom datetime disimpan **UTC** (D-20).
- Domain model memakai **UUID string PK**; jangan asumsikan integer key.
- Detail arsitektur & konvensi proyek tercantum di `../AGENTS.md`.

## Lisensi

Proyek internal — kode sumber mengikuti lisensi yang berlaku di repositori workspace ini.
