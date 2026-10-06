<?php

namespace App\Services\Aios;

use App\Enums\ProjectStatus;
use App\Jobs\RunAgentTask;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Room;
use App\Models\Task;
use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;
use App\Services\SettingsService;

/**
 * Agen Manager: satu kolom perintah, dia yang membagi tugas.
 *
 * Pengguna memerintah dalam bahasa sehari-hari ("perbaiki API login lalu
 * buatkan tesnya"), PM memecahnya menjadi subtugas dan menugaskan ke agen
 * yang rolenya paling cocok. Tugas ad-hoc ditampung di proyek inbox per
 * room agar terlacak di progres, biaya, dan repo Git seperti tugas biasa.
 */
class PmDispatcher
{
    public function __construct(
        private NineRouterClient $models,
        private SettingsService $settings,
    ) {}

    /**
     * Rencana tanpa menulis apa pun: daftar (agen, judul) yang akan ditugaskan.
     *
     * @return array{assignments: list<array{agent: Agent, title: string}>, project: Project}
     *
     * @throws CommandException
     */
    public function preview(Room $room, string $instruction, ?Project $project = null): array
    {
        $instruction = trim($instruction);

        if ($instruction === '') {
            throw new CommandException('Perintah untuk manager wajib diisi.');
        }

        $room->loadMissing('agents.role');
        $agents = $this->activeAgents($room);

        if ($agents === []) {
            throw new CommandException("Room-{$room->number} belum punya agen aktif.");
        }

        return [
            'assignments' => $this->plan($room, $agents, $instruction),
            'project' => $project ?? $this->inbox($room),
        ];
    }

    /**
     * Bagi tugas dan kirim ke antrean. Mengembalikan tugas yang dibuat.
     *
     * @return array{tasks: list<Task>, message: string}
     *
     * @throws CommandException
     */
    public function dispatch(Room $room, string $instruction, ?Project $project = null): array
    {
        ['assignments' => $assignments, 'project' => $project] = $this->preview($room, $instruction, $project);

        if ($project->status !== ProjectStatus::Running) {
            $project->status = ProjectStatus::Running;
            $project->save();
        }

        $step = ($project->tasks()->max('step') ?? 0) + 1;
        $tasks = [];

        foreach ($assignments as $item) {
            $tasks[] = Task::create([
                'project_id' => $project->id,
                'stage' => 'directive',
                'title' => mb_substr($item['title'], 0, 255),
                'step' => $step,
                'agent_id' => $item['agent']->id,
            ]);
        }

        foreach ($tasks as $task) {
            RunAgentTask::dispatch($task);
        }

        $names = collect($tasks)->map(fn (Task $t) => "'{$t->title}' → {$t->agent->slug}")->implode('; ');

        return [
            'tasks' => $tasks,
            'message' => 'Manager membagi '.count($tasks).' tugas di '.$project->name.': '.$names.'.',
        ];
    }

    /** @return list<Agent> */
    private function activeAgents(Room $room): array
    {
        return $room->agents->filter(
            fn (Agent $agent) => ! $agent->trashed() && $agent->status->value === 'active' && $agent->role !== null
        )->values()->all();
    }

    private function inbox(Room $room): Project
    {
        return Project::firstOrCreate(
            ['slug' => 'inbox-room-'.$room->number],
            [
                'name' => 'Inbox Room-'.$room->number,
                'idea' => 'Tugas ad-hoc dari perintah manager.',
                'room_id' => $room->id,
                'status' => ProjectStatus::Running,
            ]
        );
    }

    /**
     * @param  list<Agent>  $agents
     * @return list<array{agent: Agent, title: string}>
     *
     * @throws CommandException
     */
    private function plan(Room $room, array $agents, string $instruction): array
    {
        if ($this->settings->getBool('nine_router.mock', (bool) config('aios.nine_router.mock')) || $this->settings->getApiKey() === null) {
            return $this->keywordPlan($agents, $instruction);
        }

        return $this->modelPlan($room, $agents, $instruction);
    }

    /**
     * Mode tanpa model: pecah instruksi per kata sambung, cocokkan kata
     * kunci ke nama/deskripsi/output role. Deterministik untuk demo.
     *
     * @param  list<Agent>  $agents
     * @return list<array{agent: Agent, title: string}>
     */
    private function keywordPlan(array $agents, string $instruction): array
    {
        $parts = preg_split('/\s+(?:dan|lalu|kemudian)\s+|;\s*/iu', $instruction) ?: [$instruction];
        $parts = array_values(array_filter(array_map('trim', array_slice($parts, 0, 4))));

        if ($parts === []) {
            $parts = [trim($instruction)];
        }

        $assignments = [];

        foreach ($parts as $part) {
            $assignments[] = ['agent' => $this->bestKeywordMatch($agents, $part), 'title' => $part];
        }

        return $assignments;
    }

    /** @param list<Agent> $agents */
    private function bestKeywordMatch(array $agents, string $text): Agent
    {
        $words = array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [],
            fn ($word) => mb_strlen($word) > 3
        ));

        $best = $agents[0];
        $bestScore = -1;

        foreach ($agents as $agent) {
            $name = mb_strtolower($agent->role->name.' '.($agent->role->desc ?? ''));
            $outputs = mb_strtolower(implode(' ', $agent->role->outputs ?? []));
            $score = 0;

            foreach ($words as $word) {
                foreach ($this->stems($word) as $stem) {
                    if (mb_strlen($stem) < 3) {
                        continue;
                    }

                    if (str_contains($outputs, $stem)) {
                        $score += 3;
                        break;
                    }

                    if (str_contains($name, $stem)) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $best = $agent;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** Varian kata tanpa imbuhan akhir umum agar "tesnya" cocok dengan "tes". */
    private function stems(string $word): array
    {
        $variants = [$word];

        foreach (['nya', 'kan', 'an', 'lah', 'kah', 'pun', 'i'] as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 3) {
                $variants[] = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            }
        }

        return $variants;
    }

    /**
     * @param  list<Agent>  $agents
     * @return list<array{agent: Agent, title: string}>
     *
     * @throws CommandException
     */
    private function modelPlan(Room $room, array $agents, string $instruction): array
    {
        $roles = collect($agents)->map(
            fn (Agent $agent) => "- {$agent->role->slug} ({$agent->role->name}): ".implode(', ', $agent->role->outputs ?? [])
        )->implode("\n");

        try {
            $result = $this->models->chat([
                ['role' => 'system', 'content' => "Kamu manajer studio software. Bagi permintaan menjadi MAKSIMAL 5 subtugas. Jawab HANYA JSON: [{\"role\": \"slug-role\", \"title\": \"perintah singkat\"}].\n\nRole yang tersedia:\n{$roles}"],
                ['role' => 'user', 'content' => $instruction],
            ], null, ['temperature' => 0, 'max_tokens' => 512]);
        } catch (NineRouterException $e) {
            throw new CommandException('Manager gagal merencanakan: '.$e->getMessage());
        }

        $items = json_decode($this->stripFences($result['content']), true);

        if (! is_array($items) || $items === []) {
            throw new CommandException('Manager tidak menghasilkan rencana yang valid.');
        }

        $byRole = [];
        foreach ($agents as $agent) {
            $byRole[$agent->role->slug] = $agent;
        }

        $assignments = [];

        foreach (array_slice($items, 0, 5) as $item) {
            $slug = (string) ($item['role'] ?? '');
            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $assignments[] = [
                'agent' => $byRole[$slug] ?? $this->bestKeywordMatch($agents, $title.' '.$slug),
                'title' => $title,
            ];
        }

        if ($assignments === []) {
            throw new CommandException('Manager tidak menghasilkan rencana yang valid.');
        }

        return $assignments;
    }

    private function stripFences(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```[a-zA-Z]*\n?/', '', $content);
            $content = (string) preg_replace('/```\s*$/', '', $content);
        }

        return trim($content);
    }
}
