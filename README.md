# AIOS-SE (AI Orchestrator System — Special Edition)

Platform multi-agen self-hosted yang membangun perangkat lunak dari ide sampai teruji.
Satu room berisi tim agen (analis, PRD, UI/UX, database, frontend, backend, QA) yang
bekerja lewat serah terima artefak, dengan manusia di gerbang persetujuan.

Model AI diakses **hanya via 9Router** (OpenAI-compatible, combo + fallback di 9Router).

## Arsitektur singkat

- `app/Services/Aios/CommandParser.php` + `CommandExecutor.php` — perintah satu baris
  (`room add`, `agent add`, `role add`, `agent set`, `room show/link/unlink`, `project run`)
  via CLI `php artisan aios "..."` maupun halaman Konsol (pratinjau wajib).
- `app/Services/Aios/Orchestrator.php` — pipeline 9 tahap (`config/aios.php`),
  pencocokan agen via `Role.outputs`, gerbang PRD/desain/skema/rilis, delegasi
  berbatas (depth 4, 5 putaran), jeda/lanjut/henti/ulangi.
- `app/Jobs/RunAgentTask.php` — eksekusi tahap di antrean: prompt dari instruksi
  role + artefak input → artefak berversi → commit Git → (QA) verifikasi sandbox.
- `app/Services/NineRouter/NineRouterClient.php` — adaptor model + catat `costs`.
  Mode mock (`NINEROUTER_MOCK=true` / Settings) untuk demo tanpa API key.
- `app/Services/Aios/GitService.php` — repo Git per proyek, commit per tugas.
- `app/Services/Aios/SandboxService.php` — verifikasi lokal (repo bersih, artefak
  sesuai DB, lint PHP). Di CT Proxmox, kode dikirim via git clone per tugas.

## Syarat

- PHP 8.3+, Composer, Node 20+, Git
- Windows: SQLite untuk dev (bawaan). Produksi (CT Proxmox): PostgreSQL.

## Jalan cepat (dev Windows)

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve                    # http://127.0.0.1:8000
php artisan queue:work               # wajib: eksekutor tahap agen
```

Akun testing (password `password`): `owner@example.com`, `manager@example.com`,
`viewer@example.com`. Registrasi web hanya untuk pemilik pertama; akun lain via:

```powershell
php artisan user:add tim@example.com --role=manager
```

## Konfigurasi 9Router

Lewat menu **Settings** (owner): base URL, API key (terenkripsi), combo bawaan,
timeout, mode mock, anggaran token, batas delegasi. Atau via `.env`
(`NINEROUTER_BASE_URL`, `NINEROUTER_API_KEY`, `NINEROUTER_DEFAULT_COMBO`,
`NINEROUTER_MOCK`). Uji koneksi: `php artisan nine:ping` atau tombol Test koneksi.

## Alur demo tanpa API key

1. Aktifkan mode mock di Settings.
2. Room → + Add Room → tambah 9 agen (satu per role).
3. Konsol: `project run --room 01 'Aplikasi inventaris gudang'`.
4. Worker mengerjakan 9 tahap; setujui 4 gerbang di menu Persetujuan.
5. Hasil: 9 artefak berversi + repo Git per proyek + biaya tercatat.

## Produksi (Proxmox, ringkas)

- CT AIOS-SE: aplikasi Laravel + PostgreSQL + worker + nginx (hanya `public/`),
  user biasa `aios`, `.env` izin 600.
- CT 9Router: hanya terima koneksi internal dari CT AIOS-SE.
- CT sandbox: unprivileged, antrean tunggal; ganti driver `SandboxService`
  ke eksekusi via `git clone` + SSH saat CT siap.

## Perintah penting

| Perintah | Fungsi |
| --- | --- |
| `php artisan aios "room add 'IT Team'"` | Satu-baris CLI (kutip satu string) |
| `php artisan nine:ping` | Uji 9Router |
| `php artisan user:add` | Buat akun |
| `php artisan test` | Suite (pint + phpunit) |

## Status

Fase 0 (fondasi, perintah, adaptor model, web console, auth) dan Fase 1
(orkestrasi, gerbang, delegasi, Git/sandbox lokal) selesai. Berikutnya: deploy
produksi CT, sandbox terisolasi penuh, marketplace role (P2).
