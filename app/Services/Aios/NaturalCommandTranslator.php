<?php

namespace App\Services\Aios;

use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;

/**
 * Menerjemahkan bahasa alami menjadi perintah formal (PRD FR-08/FR-09).
 *
 * Hasilnya SELALU lewat pratinjau + konfirmasi (FR-10): service ini hanya
 * mengusulkan, tidak mengeksekusi. Mode mock tak bisa menerjemahkan karena
 * butuh model sungguhan.
 */
class NaturalCommandTranslator
{
    private const SYSTEM = <<<'PROMPT'
Kamu penerjemah perintah AIOS-SE. Ubah permintaan pengguna menjadi SATU perintah formal.
Jawab HANYA JSON: {"command": "...", "positional": [...], "options": {...}}

Perintah yang ada:
- room add 'Nama' [--number NN] [--purpose TEXT]
- room show NOMOR | room link A B | room unlink A B | room archive NOMOR
- agent add --room NOMOR --role 'Nama Role' [--name NAMA] [--model COMBO]
- agent set SLUG [--room NOMOR] [--model COMBO] [--role NAMA] [--status active|disabled] [--name NAMA]
- agent remove SLUG [--room NOMOR]
- role add 'Nama' [--desc TEKS] [--tools a,b] [--inputs a,b] [--outputs a,b] [--combo NAMA]
- project run --room NOMOR 'Judul' [--idea TEKS]

Bila permintaan tak jelas atau di luar daftar, jawab: {"error": "penjelasan singkat"}.
Contoh: "Tambahkan Security Specialist ke Room-01" →
{"command": "role.add", "positional": ["Security Specialist"], "options": {}}.
PROMPT;

    public function __construct(
        private NineRouterClient $models,
        private CommandParser $parser,
    ) {}

    /**
     * @return array{ok: bool, message: string, parsed?: array}
     */
    public function translate(string $input): array
    {
        try {
            $result = $this->models->chat(
                [
                    ['role' => 'system', 'content' => self::SYSTEM],
                    ['role' => 'user', 'content' => $input],
                ],
                null,
                ['temperature' => 0, 'max_tokens' => 256]
            );
        } catch (NineRouterException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $data = json_decode($this->stripFences($result['content']), true);

        if (! is_array($data) || isset($data['error'])) {
            return ['ok' => false, 'message' => is_array($data) ? ($data['error'] ?? 'Terjemahan gagal.') : 'Model tidak menjawab JSON yang valid.'];
        }

        [$domain, $action] = explode('.', ($data['command'] ?? '').'.');

        $parsed = $this->parser->parse(
            $this->compose($domain, $action, $data['positional'] ?? [], $data['options'] ?? [])
        );

        if (($parsed['type'] ?? '') !== 'command') {
            return ['ok' => false, 'message' => $parsed['error'] ?? 'Hasil terjemahan bukan perintah valid.'];
        }

        return ['ok' => true, 'message' => 'Diterjemahkan.', 'parsed' => $parsed];
    }

    private function stripFences(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\n?/', '', $content);
            $content = preg_replace('/```\s*$/', '', (string) $content);
        }

        return trim((string) $content);
    }

    private function compose(string $domain, string $action, mixed $positional, mixed $options): string
    {
        $parts = [$domain, $action];

        foreach ((array) $positional as $item) {
            $item = (string) $item;
            $parts[] = str_contains($item, ' ') ? "'{$item}'" : $item;
        }

        foreach ((array) $options as $key => $value) {
            if ($value === true) {
                $parts[] = "--{$key}";
            } elseif ($value !== false && $value !== null && $value !== '') {
                $value = (string) $value;
                $parts[] = str_contains($value, ' ') ? "--{$key} '{$value}'" : "--{$key} {$value}";
            }
        }

        return implode(' ', $parts);
    }
}
