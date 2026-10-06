# Acceptance Test AIOS-SE v1.0 — Gate 1–3

Checklist ini penutup tiap fase (PRD: 4 fase, 3 gerbang). Fase berikutnya
dimulai setelah semua item gerbangnya ✅. Mode mock boleh untuk Gate 1–2,
kecuali item bertanda 🔑 yang wajib combo 9Router asli.

## 0. Lingkungan (semua gate)

CT AIOS-SE: **PHP 8.3** (`composer.json` mensyaratkan `^8.3` — 8.3/8.4 lolos),
PostgreSQL, nginx (hanya `public/`), worker `queue:work` hidup, `.env` izin 600.

```bash
apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-pgsql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-sqlite3 git
php -v            # 8.3.x
composer install --no-dev
php artisan migrate --seed --force
npm install && npm run build
php artisan nine:ping   # 🔑 OK sebelum Gate 2
```

## Gate 1 — Fondasi (Fase 0)

- [ ] `room add 'IT Team'` → Room-01; `room show 01` tampil
- [ ] `agent add --room 01 --role 'Backend Dev' --model combo-coding` → slug otomatis
- [ ] `agent set backend-dev-1 --model combo-hemat` tanpa ganggu proyek
- [ ] `role add 'Security Specialist' --desc ... --tools git,sandbox` + muncul di Agen & Role
- [ ] Perintah hapus/arsip tanpa `--force` meminta konfirmasi; `--preview` tak mengubah data
- [ ] Web: + Add Room, tambah agen, Settings tersimpan + Test koneksi ✅
- [ ] Registrasi pertama jadi owner; registrasi kedua ditutup; `user:add` bisa login
- [ ] Viewer: bisa lihat, 403 saat simpan/pengaturan; Settings hanya owner
- [ ] Tiap eksekusi tercatat di `command_histories` + `audit_logs`
- [ ] `php artisan test` hijau (pint + phpunit)

## Gate 2 — Orkestrasi (Fase 1)

- [ ] 🔑 Konsol: `Tambahkan Security Specialist ke Room-01` → pratinjau formal → konfirmasi → jalan
- [ ] `project run --room 01 'Aplikasi inventaris gudang'` → 9 tahap terencana & terkirim
- [ ] Alur berhenti di 4 gerbang (prd → design → schema → release); setujui berurutan di Persetujuan
- [ ] Tolak gerbang → proyek jeda + tahap failed + alasan tercatat
- [ ] Tahap tanpa agen dilaporkan (`unassigned`), bukan gagal diam-diam
- [ ] Delegasi: depth > 4 dan putaran > 5 ditolak dengan pesan lanjut/henti
- [ ] Jeda/lanjut/hentikan/ulangi tahap berfungsi dari halaman proyek
- [ ] Ubah urutan + hapus tahap per proyek (terkunci setelah berjalan); reset bawaan
- [ ] Anggaran: tugas ≥ 200rb token dijeda; total proyek ≥ budget → proyek dijeda
- [ ] Viewer non-anggota: 403 halaman proyek + tak lihat di daftar; anggota bisa lihat
- [ ] Biaya tercatat 100% per agen/tahap (menu Biaya) — target serah terima ≥ 90% tanpa intervensi

## Gate 3 — Eksekusi & Rilis lokal (Fase 2)

- [ ] Repo terbentuk di `/var/www/folder-proyek/NAMA-PROYEK` (bukan di web root)
- [ ] 1 commit per tugas (`git log` = 1 init + N tahap), SHA di meta artefak
- [ ] QA gagal (berkas diubah manual) → tahap failed → Ulangi → lolos
- [ ] Aplikasi dibuka di port dev, menunggu persetujuan rilis akhir
- [ ] 0 aksi hapus/deploy/paket tanpa persetujuan (cek audit log)
- [ ] Acuan: proyek `aplikasi-inventaris-gudang` (done, 9/9 artefak, 4/4 approval)

## Metrik (target usulan PRD)

| Metrik | Target | Cara ukur |
| --- | --- | --- |
| Tambah agen/role | ≤ 1 mnt, 1 perintah | Uji pengguna + riwayat |
| Sampel CRUD | ide → tes lulus | Gate 3 |
| Serah terima tanpa intervensi | ≥ 90% | log eksekusi |
| Aksi berisiko tanpa izin | 0 | audit log |
| Biaya tercatat | 100% | menu Biaya |
