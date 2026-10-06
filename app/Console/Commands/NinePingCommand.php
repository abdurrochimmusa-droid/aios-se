<?php

namespace App\Console\Commands;

use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;
use Illuminate\Console\Command;

class NinePingCommand extends Command
{
    protected $signature = 'nine:ping
        {--combo= : Nama combo 9Router yang diuji (bawaan: NINEROUTER_DEFAULT_COMBO)}
        {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Uji konektivitas ke 9Router dengan satu pesan ringan';

    public function handle(NineRouterClient $client): int
    {
        $combo = $this->option('combo') ?? (string) config('aios.nine_router.default_combo');

        try {
            $result = $client->chat(
                [['role' => 'user', 'content' => 'Balas hanya dengan kata: ok']],
                $combo,
                ['max_tokens' => 16]
            );
        } catch (NineRouterException $e) {
            return $this->failWith($e->getMessage());
        }

        $summary = "9Router OK: combo '{$result['model']}', {$result['tokens_in']}+{$result['tokens_out']} token, {$result['latency_ms']} ms.";

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => true] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info($summary);
            $this->line('Jawaban model: '.mb_substr(trim($result['content']), 0, 120));
        }

        return 0;
    }

    private function failWith(string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['ok' => false, 'message' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error($message);
        }

        return 1;
    }
}
