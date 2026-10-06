<?php

namespace App\Services\NineRouter;

use App\Models\Agent;
use App\Models\Cost;
use App\Services\SettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Satu-satunya jalan keluar ke model AI (PRD FR-21).
 *
 * Berbicara OpenAI-compatible ke 9Router: POST {baseUrl}/chat/completions
 * dengan Bearer API key. Combo dan fallback antarmodel dikelola di 9Router;
 * AIOS-SE hanya mengirim nama combo sebagai `model`.
 *
 * - Timeout eksplisit dari config/aios.php (connect 10 dtk, respons NINEROUTER_TIMEOUT).
 * - Retry hanya untuk kegagalan transien (koneksi, 429, 5xx). Tiap percobaan
 *   ulang dapat menambah pemakaian di sisi 9Router; kegagalan permanen (4xx
 *   selain 429, payload tak valid) langsung dilempar tanpa retry.
 * - chatForAgent() mencatat Cost per agen/room/proyek/tahap untuk dashboard biaya.
 */
class NineRouterClient
{
    public function __construct(private SettingsService $settings) {}

    /** @return array{content: string, model: string, tokens_in: int, tokens_out: int, latency_ms: int} */
    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $model ??= $this->settings->get('nine_router.default_combo', (string) config('aios.nine_router.default_combo'));

        if ($this->settings->getBool('nine_router.mock', (bool) config('aios.nine_router.mock'))) {
            return $this->mock($messages, $model, $options);
        }

        $baseUrl = rtrim((string) $this->settings->get('nine_router.base_url', (string) config('aios.nine_router.base_url')), '/');
        $apiKey = $this->settings->getApiKey();

        if ($apiKey === null || $apiKey === '') {
            throw new NineRouterException('API key 9Router belum diisi. Buka menu Settings untuk mengisinya.');
        }

        $started = (int) (microtime(true) * 1000);

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->connectTimeout(10)
                ->timeout($this->settings->getInt('nine_router.timeout', (int) config('aios.nine_router.timeout')))
                ->retry([200, 500, 1500], 2, function (Throwable $e) {
                    if ($e instanceof ConnectionException) {
                        return true;
                    }

                    return $e instanceof RequestException
                        && ($e->response->serverError() || $e->response->status() === 429);
                }, throw: false)
                ->post('/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.2,
                    'max_tokens' => $options['max_tokens'] ?? 2048,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new NineRouterException(
                '9Router menolak permintaan ('.$e->response->status().'): '.mb_substr($e->response->body(), 0, 300),
                previous: $e
            );
        } catch (ConnectionException $e) {
            throw new NineRouterException('Tidak dapat mencapai 9Router di '.$baseUrl.'.', previous: $e);
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new NineRouterException('Respons 9Router tidak berisi choices[0].message.content.');
        }

        return [
            'content' => $content,
            'model' => $response['model'] ?? $model,
            'tokens_in' => (int) ($response['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($response['usage']['completion_tokens'] ?? 0),
            'latency_ms' => (int) (microtime(true) * 1000) - $started,
        ];
    }

    /**
     * Chat atas nama agen: combo dari agen → default role → default global,
     * lalu catat pemakaian ke costs.
     *
     * @param  array{project_id?: int, stage?: string}  $context
     * @return array{content: string, model: string, tokens_in: int, tokens_out: int, latency_ms: int}
     */
    public function chatForAgent(Agent $agent, array $messages, array $context = [], array $options = []): array
    {
        $agent->loadMissing('role');

        $combo = $agent->combo_name
            ?? $agent->role?->default_combo
            ?? (string) config('aios.nine_router.default_combo');

        $result = $this->chat($messages, $combo, $options + ['mock_stage' => $context['stage'] ?? 'umum']);

        Cost::create([
            'project_id' => $context['project_id'] ?? null,
            'room_id' => $agent->room_id,
            'agent_id' => $agent->id,
            'stage' => $context['stage'] ?? null,
            'model' => $result['model'],
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
            'latency_ms' => $result['latency_ms'],
        ]);

        return $result;
    }

    /**
     * Jawaban deterministik tanpa memanggil model — untuk demo alur E2E,
     * pengembangan UI, dan test yang butuh konten per tahap.
     * Token diestimasi (1 token ≈ 4 karakter) agar dashboard biaya tetap hidup.
     */
    private function mock(array $messages, string $model, array $options): array
    {
        $stage = (string) ($options['mock_stage'] ?? 'umum');
        $content = $this->mockTemplate($stage)."\n\n_Dibuat dalam mode mock (tanpa API key) memakai {$model}._";

        return [
            'content' => $content,
            'model' => $model,
            'tokens_in' => max(1, intdiv(mb_strlen(json_encode($messages)), 4)),
            'tokens_out' => max(1, intdiv(mb_strlen($content), 4)),
            'latency_ms' => 5,
        ];
    }

    private function mockTemplate(string $stage): string
    {
        return match ($stage) {
            'analysis-report' => "# Laporan Analisis Kebutuhan\n\n## Tujuan\nMengotomasi proses yang diminta pengguna.\n\n## Aktor\n- Pemilik proyek\n- Pengguna operasional\n\n## Kebutuhan fungsional\n- CRUD data utama\n- Validasi input dan hak akses\n- Laporan ringkas",
            'research-summary' => "# Ringkasan Riset\n\n## Solusi sejenis\n- Aplikasi CRUD Laravel + PostgreSQL\n- Panel admin generik\n\n## Rekomendasi\nGunakan pola resource controller, Form Request, dan Policy agar konsisten dengan AIOS-SE.",
            'prd' => "# PRD\n\n## Latar belakang\nPengguna butuh aplikasi web sederhana yang berjalan.\n\n## User stories\n- Sebagai pemilik, saya bisa menambah, melihat, mengubah, dan menghapus data.\n- Sebagai pemilik, saya bisa masuk dan mengelola hak akses.\n\n## Kriteria penerimaan\n- Semua CRUD berfungsi dan teruji otomatis.",
            'ux-flow' => "# Alur UX & Panduan Desain\n\n## Alur\nDaftar → Tambah → Ubah → Hapus, dengan konfirmasi untuk aksi destruktif.\n\n## Panduan\n- Warna primer: brand-600; aksen: emerald untuk sukses, rose untuk bahaya.\n- Radius kecil (rounded-md), tanpa dark mode pada v1.",
            'wireframe' => "# Wireframe\n\n## Layar 1: Daftar\nTabel (nama, status, aksi) + tombol + Add.\n\n## Layar 2: Form\nField nama, deskripsi, tombol Simpan/Batal + error validasi.\n\n## Layar 3: Detail\nRingkasan + riwayat perubahan.",
            'schema' => "# Skema Database (PostgreSQL)\n\n## Tabel items\n- id (bigserial, PK)\n- name (varchar 255, not null)\n- description (text, null)\n- created_at / updated_at\n\n## Indeks\n- items(name) untuk pencarian.",
            'frontend-code' => "# Kode Frontend\n\n- Tabel Blade + form dengan @csrf dan @method.\n- Komponen kartu statistik di Beranda.\n- Semua aksi destruktif memakai konfirmasi.",
            'backend-code' => "# Kode Backend\n\n- Controller resource + Form Request + Policy per peran.\n- Relasi Eloquent sesuai skema; antrean untuk pekerjaan agen.\n- Tes fitur untuk tiap endpoint.",
            'test-report' => "# Laporan Tes\n\n## Hasil\n- 85 skenario dijalankan, 85 lulus.\n\n## Bug\n- Tidak ada bug terbuka; temuan kecil didokumentasikan sebagai tindak lanjut.",
            default => "# Hasil tahap {$stage}\n\nKonten generik mode mock.",
        };
    }
}
