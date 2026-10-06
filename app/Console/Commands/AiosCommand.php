<?php

namespace App\Console\Commands;

use App\Services\Aios\CommandExecutor;
use Illuminate\Console\Command;

class AiosCommand extends Command
{
    protected $signature = 'aios
        {input* : Perintah satu baris, mis. room add \'IT Team\'}
        {--force : Lewati konfirmasi untuk perintah destruktif}
        {--preview : Tampilkan pratinjau tanpa mengeksekusi}
        {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Perintah satu baris AIOS-SE untuk room, agen, role, dan proyek';

    public function handle(CommandExecutor $executor): int
    {
        $input = implode(' ', (array) $this->argument('input'));
        $parsed = $executor->parse($input);

        if ($parsed['type'] === 'empty') {
            return $this->failWith('Perintah kosong. Contoh: aios room add \'IT Team\'.');
        }

        if ($parsed['type'] === 'natural') {
            $executor->recordPreview($parsed);
            $message = 'Bahasa alami diterjemahkan menjadi perintah formal, lalu dijalankan setelah konfirmasi. Penerjemah model (9Router) hadir di Fase 1 — untuk sekarang pakai sintaks formal.';

            return $this->failWith($message, 2);
        }

        if ($parsed['type'] === 'invalid') {
            return $this->failWith($parsed['error']);
        }

        $preview = $executor->preview($parsed);

        if (! $preview['ok']) {
            return $this->failWith($preview['message']);
        }

        if ($this->option('json')) {
            $this->line(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($this->option('preview')) {
                $executor->recordPreview($parsed);

                return 0;
            }
        } else {
            $this->line($preview['message']);

            if ($this->option('preview')) {
                $executor->recordPreview($parsed);

                return 0;
            }
        }

        if ($executor->isDestructive($parsed) && ! $this->option('force')) {
            if (! $this->confirm('Perintah ini mengubah arsip/hubungan/data. Jalankan?')) {
                $executor->recordCancel($parsed);
                $this->line('Dibatalkan, tidak ada yang berubah.');

                return 1;
            }
        }

        $result = $executor->run($parsed);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($result['message']);
        }

        return $result['ok'] ? 0 : 1;
    }

    private function failWith(string $message, int $code = 1): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => false, 'message' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($message);
        }

        return $code;
    }
}
