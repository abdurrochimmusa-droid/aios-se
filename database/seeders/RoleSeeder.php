<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Pustaka role bawaan Room-01-IT Team (PRD FR-07).
     * Idempotent: aman dijalankan ulang, dicocokkan per slug.
     */
    public function run(): void
    {
        $roles = [
            [
                'slug' => 'senior-data-analyst',
                'name' => 'Senior Data Analyst',
                'desc' => 'Menganalisis kebutuhan dan proses yang akan diotomasi',
                'instructions' => 'Ubah ide proyek menjadi laporan analisis kebutuhan: tujuan, lingkup, aktor, proses bisnis, dan kebutuhan fungsional. Minta klarifikasi bila ide ambigu.',
                'skills' => ['requirement-analysis', 'process-mapping'],
                'allowed_tools' => ['file', 'web'],
                'expected_inputs' => ['idea'],
                'outputs' => ['analysis-report'],
                'default_combo' => 'combo-hemat',
            ],
            [
                'slug' => 'senior-data-researcher',
                'name' => 'Senior Data Researcher',
                'desc' => 'Meneliti referensi, pesaing, dan solusi sejenis',
                'instructions' => 'Teliti referensi, pesaing, dan solusi sejenis dari sumber yang diizinkan. Hasilkan ringkasan riset: perbandingan solusi, praktik terbaik, dan rekomendasi untuk PRD.',
                'skills' => ['web-research', 'competitor-analysis'],
                'allowed_tools' => ['file', 'web'],
                'expected_inputs' => ['idea', 'analysis-report'],
                'outputs' => ['research-summary'],
                'default_combo' => 'combo-hemat',
            ],
            [
                'slug' => 'prd-specialist',
                'name' => 'PRD Specialist',
                'desc' => 'Menyusun PRD dari analisis dan riset',
                'instructions' => 'Susun PRD lengkap dari laporan analisis dan ringkasan riset: latar belakang, tujuan, persona, user stories, kebutuhan fungsional dan non-fungsional, kriteria penerimaan. PRD wajib menunggu persetujuan manusia sebelum alur lanjut.',
                'skills' => ['prd-writing', 'acceptance-criteria'],
                'allowed_tools' => ['file'],
                'expected_inputs' => ['analysis-report', 'research-summary'],
                'outputs' => ['prd'],
                'default_combo' => 'combo-hemat',
            ],
            [
                'slug' => 'ui-ux-specialist',
                'name' => 'UI/UX Specialist',
                'desc' => 'Merancang alur pengguna dan panduan visual',
                'instructions' => 'Rancang alur pengguna (user flow) dan panduan desain (warna, tipografi, komponen) berdasarkan PRD yang disetujui. Alur dan panduan menunggu persetujuan manusia.',
                'skills' => ['user-flows', 'design-systems'],
                'allowed_tools' => ['file'],
                'expected_inputs' => ['prd'],
                'outputs' => ['ux-flow', 'design-guide'],
                'default_combo' => 'combo-hemat',
            ],
            [
                'slug' => 'wireframe-specialist',
                'name' => 'Wireframe Specialist',
                'desc' => 'Membuat wireframe tiap layar',
                'instructions' => 'Buat wireframe tiap layar berdasarkan alur UX dan panduan desain. Setiap wireframe merujuk ke user story di PRD.',
                'skills' => ['wireframing', 'layout'],
                'allowed_tools' => ['file'],
                'expected_inputs' => ['ux-flow', 'design-guide'],
                'outputs' => ['wireframe'],
                'default_combo' => 'combo-hemat',
            ],
            [
                'slug' => 'database-engineer',
                'name' => 'Database Engineer',
                'desc' => 'Merancang skema dan migrasi database',
                'instructions' => 'Rancang skema PostgreSQL dari PRD dan wireframe: tabel, kolom, relasi, indeks, dan migrasi. Skema menunggu persetujuan manusia sebelum implementasi.',
                'skills' => ['postgresql', 'schema-design', 'laravel-migrations'],
                'allowed_tools' => ['file', 'db'],
                'expected_inputs' => ['prd', 'wireframe'],
                'outputs' => ['schema'],
                'default_combo' => 'combo-coding',
            ],
            [
                'slug' => 'frontend-dev',
                'name' => 'Frontend Dev',
                'desc' => 'Membangun antarmuka sesuai wireframe dan desain',
                'instructions' => 'Bangun antarmuka (Blade/Tailwind) sesuai wireframe, panduan desain, dan skema. Commit tiap tugas ke repositori Git proyek. Bekerja paralel dengan Backend Dev via kontrak artefak.',
                'skills' => ['blade', 'tailwindcss', 'laravel-frontend'],
                'allowed_tools' => ['file', 'git', 'sandbox'],
                'expected_inputs' => ['prd', 'wireframe', 'schema', 'design-guide'],
                'outputs' => ['frontend-code'],
                'default_combo' => 'combo-coding',
            ],
            [
                'slug' => 'backend-dev',
                'name' => 'Backend Dev',
                'desc' => 'Membangun API dan logika bisnis',
                'instructions' => 'Bangun API dan logika bisnis (Laravel + PostgreSQL) sesuai PRD dan skema. Ikuti konvensi proyek, tulis migrasi dan tes. Commit tiap tugas ke repositori Git proyek.',
                'skills' => ['laravel', 'eloquent', 'api-design', 'pest'],
                'allowed_tools' => ['file', 'git', 'sandbox', 'db'],
                'expected_inputs' => ['prd', 'schema'],
                'outputs' => ['backend-code'],
                'default_combo' => 'combo-coding',
            ],
            [
                'slug' => 'qa-test',
                'name' => 'QA/Test',
                'desc' => 'Menyusun dan menjalankan tes, melaporkan bug',
                'instructions' => 'Susun dan jalankan tes di sandbox atas kode frontend dan backend. Laporkan hasil sebagai laporan tes; kegagalan dikembalikan ke agen pembuat dengan langkah reproduksi. Tes wajib lulus sebelum serah terima.',
                'skills' => ['test-planning', 'pest', 'bug-reporting'],
                'allowed_tools' => ['file', 'sandbox', 'git'],
                'expected_inputs' => ['frontend-code', 'backend-code'],
                'outputs' => ['test-report'],
                'default_combo' => 'combo-coding',
            ],
        ];

        foreach ($roles as $role) {
            $payload = [
                'name' => $role['name'],
                'desc' => $role['desc'],
                'instructions' => $role['instructions'],
                'skills' => json_encode($role['skills']),
                'allowed_tools' => json_encode($role['allowed_tools']),
                'expected_inputs' => json_encode($role['expected_inputs']),
                'outputs' => json_encode($role['outputs']),
                'default_combo' => $role['default_combo'],
                'version' => 1,
                'is_builtin' => true,
                'updated_at' => now(),
            ];

            $updated = DB::table('roles')->where('slug', $role['slug'])->update($payload);

            if ($updated === 0) {
                DB::table('roles')->insert($payload + [
                    'slug' => $role['slug'],
                    'created_at' => now(),
                ]);
            }
        }
    }
}
