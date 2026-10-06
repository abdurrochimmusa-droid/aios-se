<?php

namespace App\Services\Aios;

/**
 * Menerjemahkan satu baris perintah menjadi struktur formal.
 *
 * Sintaks v1.0 (awalan `aios` hanya di CLI, dihilangkan di kolom web):
 *   room add 'IT Team' | room show 01 | room link 01 02 | room unlink 01 02
 *   agent add --room 01 --role 'Backend Dev' --model combo-coding
 *   agent set backend-dev-1 --model combo-hemat
 *   role add 'Security Specialist' --desc '...' --tools git,sandbox
 *   project run --room 01 'Aplikasi inventaris gudang'
 *
 * Input yang tidak cocok dengan tata bahasa dikembalikan sebagai
 * `natural` agar executor meminta konfirmasi setelah diterjemahkan.
 */
class CommandParser
{
    private const DOMAINS = [
        'room' => ['add', 'show', 'link', 'unlink', 'archive'],
        'agent' => ['add', 'set', 'remove', 'show'],
        'role' => ['add'],
        'project' => ['run', 'progress'],
        'task' => ['revise'],
        'pm' => ['send'],
    ];

    public function parse(string $input): array
    {
        $tokens = $this->tokenize($input);

        if ($tokens === []) {
            return ['type' => 'empty', 'raw' => $input];
        }

        $domain = strtolower($tokens[0]);

        if (! isset(self::DOMAINS[$domain])) {
            return ['type' => 'natural', 'raw' => $input];
        }

        $action = strtolower($tokens[1] ?? '');

        if (! in_array($action, self::DOMAINS[$domain], true)) {
            return [
                'type' => 'invalid',
                'raw' => $input,
                'error' => "Aksi '{$tokens[1]}' tidak dikenal untuk '{$domain}'.",
            ];
        }

        [$positional, $options] = $this->splitArgs(array_slice($tokens, 2));

        return [
            'type' => 'command',
            'domain' => $domain,
            'action' => $action,
            'command' => "{$domain}.{$action}",
            'positional' => $positional,
            'options' => $options,
            'raw' => $input,
        ];
    }

    /** @return list<string> */
    public function tokenize(string $input): array
    {
        preg_match_all("/'([^']*)'|\"([^\"]*)\"|(\\S+)/u", $input, $m);

        $tokens = [];
        foreach ($m[0] as $i => $full) {
            if ($m[1][$i] !== '' || str_contains($full, "'")) {
                $tokens[] = $m[1][$i];
            } elseif ($m[2][$i] !== '' || str_contains($full, '"')) {
                $tokens[] = $m[2][$i];
            } else {
                $tokens[] = $m[3][$i];
            }
        }

        return $tokens;
    }

    /** @return array{list<string>, array<string, string|bool>} */
    private function splitArgs(array $tokens): array
    {
        $positional = [];
        $options = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (str_starts_with($token, '--')) {
                $key = substr($token, 2);

                if (str_contains($key, '=')) {
                    [$key, $value] = explode('=', $key, 2);
                    $options[$key] = $value;
                } elseif (isset($tokens[$i + 1]) && ! str_starts_with($tokens[$i + 1], '--')) {
                    $options[$key] = $tokens[++$i];
                } else {
                    $options[$key] = true;
                }
            } else {
                $positional[] = $token;
            }
        }

        return [$positional, $options];
    }
}
