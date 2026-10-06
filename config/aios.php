<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 9Router Model Gateway
    |--------------------------------------------------------------------------
    | Satu-satunya jalan keluar ke model AI. Combo dan fallback dikelola
    | di 9Router; AIOS-SE hanya menyimpan nama combo per agen.
    */
    'nine_router' => [
        'base_url' => env('NINEROUTER_BASE_URL', 'http://localhost:8787/v1'),
        'api_key' => env('NINEROUTER_API_KEY'),
        'default_combo' => env('NINEROUTER_DEFAULT_COMBO', 'combo-hemat'),
        'timeout' => (int) env('NINEROUTER_TIMEOUT', 120),
        'mock' => (bool) env('NINEROUTER_MOCK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lokasi proyek dan artefak
    |--------------------------------------------------------------------------
    | Produksi (CT AIOS-SE): /var/www/folder-proyek/NAMA-PROYEK
    | Dev Windows: storage/app/projects (override via .env)
    */
    'projects_root' => env('AIOS_PROJECTS_ROOT', storage_path('app/projects')),
    'artifacts_dir' => env('AIOS_ARTIFACTS_DIR', 'artefak'),

    /*
    |--------------------------------------------------------------------------
    | Batas orkestrasi bawaan (PRD FR-19, FR-24)
    */
    'task_token_budget' => (int) env('AIOS_TASK_TOKEN_BUDGET', 200000),
    'delegation' => [
        'max_depth' => (int) env('AIOS_DELEGATION_MAX_DEPTH', 4),
        'max_rounds' => (int) env('AIOS_DELEGATION_MAX_ROUNDS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alur pengembangan bawaan (Fase 1)
    |--------------------------------------------------------------------------
    | Satu entri = satu tahap. `outputs` dicocokkan ke Role.outputs untuk
    | memilih agen; entri se-step berjalan paralel. `gate` menahan alur
    | sampai manusia menyetujui; `release_after` membuka gerbang rilis akhir.
    */
    'pipeline' => [
        ['stage' => 'analysis-report', 'title' => 'Analisis kebutuhan', 'step' => 1, 'gate' => null, 'outputs' => ['analysis-report']],
        ['stage' => 'research-summary', 'title' => 'Riset solusi', 'step' => 2, 'gate' => null, 'outputs' => ['research-summary']],
        ['stage' => 'prd', 'title' => 'PRD', 'step' => 3, 'gate' => 'prd', 'outputs' => ['prd']],
        ['stage' => 'ux-flow', 'title' => 'Desain UX', 'step' => 4, 'gate' => null, 'outputs' => ['ux-flow', 'design-guide']],
        ['stage' => 'wireframe', 'title' => 'Wireframe', 'step' => 5, 'gate' => 'design', 'outputs' => ['wireframe']],
        ['stage' => 'schema', 'title' => 'Skema database', 'step' => 6, 'gate' => 'schema', 'outputs' => ['schema']],
        ['stage' => 'frontend-code', 'title' => 'Frontend', 'step' => 7, 'gate' => null, 'outputs' => ['frontend-code']],
        ['stage' => 'backend-code', 'title' => 'Backend', 'step' => 7, 'gate' => null, 'outputs' => ['backend-code']],
        ['stage' => 'test-report', 'title' => 'QA & tes', 'step' => 8, 'gate' => null, 'outputs' => ['test-report'], 'release_after' => true],
    ],
];
