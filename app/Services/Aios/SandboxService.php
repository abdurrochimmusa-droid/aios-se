<?php

namespace App\Services\Aios;

use App\Models\Project;
use Symfony\Component\Process\Process;

/**
 * Verifikasi sandbox, driver lokal (PRD FR-17, adaptasi tanpa CT).
 *
 * Di CT Proxmox produksi, kode dikirim via git clone per tugas dan tes
 * dijalankan terisolasi. Di sini driver lokal memverifikasi hal yang nyata
 * dan dapat dicek: repo ada & bersih, tiap artefak tertulis di disk sesuai
 * isi DB, dan file PHP (bila ada) lolos `php -l`. Hasil masuk ke meta
 * artefak QA; bila gagal, tahap QA ditandai failed dan dikembalikan
 * ke antrean lewat tombol Ulangi.
 */
class SandboxService
{
    public function __construct(private GitService $git) {}

    /** @return array{ok: bool, checks: array<string, string>} */
    public function verify(Project $project): array
    {
        $checks = [];
        $path = $this->git->repoPath($project);

        if (! is_dir($path.'/.git')) {
            return ['ok' => false, 'checks' => ['repo' => 'repositori git belum diinisialisasi']];
        }
        $checks['repo'] = 'repositori git ada';

        $dirty = trim($this->gitLog($path, ['git', 'status', '--porcelain']));
        $checks['bersih'] = $dirty === '' ? 'working tree bersih' : 'ada perubahan belum ter-commit';

        $missing = [];
        $artifacts = $project->artifacts()->get();

        foreach ($artifacts as $artifact) {
            $file = $path.'/artefak/'.$this->git->filename($artifact);
            if (! is_file($file) || file_get_contents($file) !== ($artifact->body ?? '')) {
                $missing[] = $artifact->type.' v'.$artifact->version;
            }
        }
        $checks['artefak'] = $missing === [] ? count($artifacts).' berkas sesuai DB' : 'tidak cocok: '.implode(', ', $missing);

        $lint = $this->lintPhp($path);
        $checks['php-lint'] = $lint;

        $ok = $missing === [] && $dirty === '' && ! str_starts_with($lint, 'GAGAL');

        return ['ok' => $ok, 'checks' => $checks];
    }

    private function gitLog(string $path, array $command): string
    {
        $process = new Process($command, $path);
        $process->setTimeout(60);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : '';
    }

    private function lintPhp(string $path): string
    {
        $files = [];
        foreach (['*.php', 'artefak/*.php', 'app/*.php', 'routes/*.php'] as $pattern) {
            foreach (glob($path.'/'.$pattern) ?: [] as $file) {
                $files[] = $file;
            }
        }

        if ($files === []) {
            return 'tidak ada berkas PHP (artefak dokumen saja)';
        }

        foreach ($files as $file) {
            $process = new Process([PHP_BINARY, '-l', $file]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                return 'GAGAL: '.basename($file);
            }
        }

        return count($files).' berkas PHP lolos lint';
    }
}
