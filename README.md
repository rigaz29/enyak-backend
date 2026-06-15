# Enyak Backend — Rembuk TV (Fase 1)

Backend langganan untuk app **Rembuk TV**. Plain PHP + PDO + MySQL, didesain untuk
**shared hosting**. Lihat desain lengkap di repo app: `docs/subscription-brief.md`.

## Yang ada di Fase 1
- Skema DB (`devices`, `channels`, `admins`, `activation_logs`, `settings`).
- `POST /v1/sync` — registrasi device (device baru → **trial 1 jam**), balikan entitlement
  + katalog channel terfilter (URL proxy ber-token) + config.
- `GET /v1/config` — remote config (URL website, video promo, min versi app).
- `GET /s/{channelId}?token=…` — **proxy stream**: validasi token → **HTTP 302** ke URL asli.
- Enforcement: channel paid **tanpa URL** untuk device free; URL asli tak pernah dikirim ke app.

> Belum termasuk: dashboard admin & website langganan (Fase 3–4). Tabel `admins` sudah ada.

## Struktur
```
public/        docroot (arahkan domain ke sini)
  index.php    front controller / router
  .htaccess    rewrite ke index.php
src/           kode (autoload Enyak\ -> src/)
config/        config.example.php  (salin -> config.php, gitignored)
sql/           schema.sql + seed.sql
tools/         make_admin.php (CLI)
```

## Deploy ke shared hosting
1. **Buat database MySQL** + user (lewat cPanel), catat nama db/user/pass.
2. **Import** `sql/schema.sql` lalu `sql/seed.sql` (phpMyAdmin / Import).
3. Salin `config/config.example.php` → `config/config.php`, isi kredensial DB,
   `proxy_base` = `https://api.enyak.my.id`, dan **`token_secret`** (acak panjang —
   `php -r "echo bin2hex(random_bytes(32));"`).
4. **Arahkan subdomain** `api.enyak.my.id` ke folder **`public/`** (Document Root).
   (Kalau tak bisa ubah docroot, taruh isi `public/` di root subdomain & sesuaikan
   path `require` ke `src/`.)
5. Pastikan **HTTPS** aktif (Let's Encrypt cPanel) — wajib.

## Tes cepat (curl)
```bash
# health
curl https://api.enyak.my.id/

# sync device baru -> dapat status "trial" + daftar channel (yg paid: locked, tanpa url)
curl -X POST https://api.enyak.my.id/v1/sync \
  -H 'Content-Type: application/json' \
  -d '{"deviceId":"abcdef0123456789","appVersion":"1.0.0"}'

# proxy stream: ambil "url" channel dari respons sync, lalu:
curl -I "https://api.enyak.my.id/s/1?token=XXXX"   # harus 302 Location: <url asli>

# config
curl https://api.enyak.my.id/v1/config
```

## Tes lokal (opsional, butuh PHP)
```bash
cp config/config.example.php config/config.php   # set DB (MySQL/atau MariaDB lokal)
php -S localhost:8000 -t public
curl -X POST localhost:8000/v1/sync -d '{"deviceId":"abcdef0123456789"}'
```

## Buat admin (untuk Fase 3)
```bash
php tools/make_admin.php admin@enyak.my.id 'PasswordKuat'
```

## Keamanan (lihat brief §11)
- `config/config.php` (DB creds + `token_secret`) **tidak** di-commit.
- URL channel asli hanya di DB; app cuma menerima URL proxy ber-token.
- Token HMAC mengikat `channelId + deviceId + exp`; device banned langsung ditolak di `/s`.
- Tambahkan **rate limiting** (mod_evasive / di app) untuk `/v1/sync` & `/s`.
