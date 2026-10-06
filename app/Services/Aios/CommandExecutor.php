<?php

namespace App\Services\Aios;

use App\Enums\AgentStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\CommandHistory;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menjalankan perintah formal hasil CommandParser.
 *
 * - preview(): rencana dalam bahasa manusia, tanpa mengubah data.
 * - run(): mengeksekusi dalam transaksi + mencatat CommandHistory dan AuditLog.
 * - Perintah destruktif (arsip/hapus/putus link) wajib dikonfirmasi
 *   pemanggil (lihat AiosCommand --force). Perintah bahasa alami selalu
 *   lewat pratinjau dan menunggu penerjemah model (Fase 1).
 */
class CommandExecutor
{
    private const DESTRUCTIVE = ['room.archive', 'room.unlink', 'agent.remove'];

    public function __construct(private CommandParser $parser) {}

    public function parse(string $input): array
    {
        return $this->parser->parse($input);
    }

    public function isDestructive(array $parsed): bool
    {
        return in_array($parsed['command'] ?? '', self::DESTRUCTIVE, true);
    }

    /** @return array{ok: bool, message: string, data: array} */
    public function preview(array $parsed): array
    {
        try {
            return match ($parsed['command']) {
                'room.add' => $this->previewRoomAdd($parsed),
                'room.show' => $this->previewRoomShow($parsed),
                'room.link' => $this->previewRoomLink($parsed, true),
                'room.unlink' => $this->previewRoomLink($parsed, false),
                'room.archive' => $this->previewRoomArchive($parsed),
                'agent.add' => $this->previewAgentAdd($parsed),
                'agent.set' => $this->previewAgentSet($parsed),
                'agent.remove' => $this->previewAgentRemove($parsed),
                'agent.show' => $this->previewAgentShow($parsed),
                'role.add' => $this->previewRoleAdd($parsed),
                'project.run' => $this->previewProjectRun($parsed),
                'project.progress' => $this->previewProjectProgress($parsed),
                'task.revise' => $this->previewTaskRevise($parsed),
                default => $this->fail('Perintah tidak didukung.'),
            };
        } catch (CommandException $e) {
            return $this->fail($e->getMessage());
        }
    }

    /** @return array{ok: bool, message: string, data: array} */
    public function run(array $parsed, ?int $userId = null): array
    {
        try {
            $result = DB::transaction(fn () => match ($parsed['command']) {
                'room.add' => $this->runRoomAdd($parsed),
                'room.show' => $this->previewRoomShow($parsed),
                'room.link' => $this->runRoomLink($parsed, true),
                'room.unlink' => $this->runRoomLink($parsed, false),
                'room.archive' => $this->runRoomArchive($parsed),
                'agent.add' => $this->runAgentAdd($parsed),
                'agent.set' => $this->runAgentSet($parsed),
                'agent.remove' => $this->runAgentRemove($parsed),
                'agent.show' => $this->previewAgentShow($parsed),
                'role.add' => $this->runRoleAdd($parsed),
                'project.run' => $this->runProjectRun($parsed),
                'project.progress' => $this->previewProjectProgress($parsed),
                'task.revise' => $this->runTaskRevise($parsed),
                default => $this->fail('Perintah tidak didukung.'),
            });
        } catch (CommandException $e) {
            return $this->fail($e->getMessage());
        }

        if ($result['ok']) {
            $this->recordHistory($parsed, 'executed', $result, $userId);
            $this->recordAudit($parsed, $result, $userId);
        }

        return $result;
    }

    public function recordPreview(array $parsed, ?int $userId = null): void
    {
        $this->recordHistory($parsed, 'preview', ['ok' => true, 'message' => '', 'data' => []], $userId);
    }

    public function recordCancel(array $parsed, ?int $userId = null): void
    {
        $this->recordHistory($parsed, 'cancelled', ['ok' => false, 'message' => 'Dibatalkan pengguna.', 'data' => []], $userId);
    }

    // ---- room ----

    private function roomName(array $parsed): string
    {
        $name = trim($parsed['positional'][0] ?? '');

        if ($name === '') {
            throw new CommandException("Nama room wajib diisi: room add 'Nama Tim'.");
        }

        return $name;
    }

    private function nextRoomNumber(): string
    {
        $max = Room::query()->pluck('number')
            ->map(fn ($n) => ctype_digit((string) $n) ? (int) $n : 0)
            ->max() ?? 0;

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    private function previewRoomAdd(array $parsed): array
    {
        $name = $this->roomName($parsed);
        $number = (string) ($parsed['options']['number'] ?? $this->nextRoomNumber());

        if (Room::where('number', $number)->exists()) {
            throw new CommandException("Room-{$number} sudah ada.");
        }

        return [
            'ok' => true,
            'message' => "Akan membuat Room-{$number} '{$name}'.",
            'data' => ['number' => $number, 'name' => $name],
        ];
    }

    private function runRoomAdd(array $parsed): array
    {
        $preview = $this->previewRoomAdd($parsed);

        $room = Room::create([
            'number' => $preview['data']['number'],
            'name' => $preview['data']['name'],
            'purpose' => $parsed['options']['purpose'] ?? null,
            'settings' => ['inter_room_enabled' => false, 'links' => []],
        ]);

        return [
            'ok' => true,
            'message' => "Room-{$room->number} '{$room->name}' dibuat.",
            'data' => ['id' => $room->id, 'number' => $room->number, 'name' => $room->name],
        ];
    }

    private function findRoom(string $ref): Room
    {
        $query = Room::query();

        if (ctype_digit($ref)) {
            $query->where(function ($q) use ($ref) {
                $q->where('number', $ref)
                    ->orWhere('number', str_pad($ref, 2, '0', STR_PAD_LEFT))
                    ->orWhere('id', (int) $ref);
            });
        } else {
            $query->where('number', $ref);
        }

        return $query->first()
            ?? throw new CommandException("Room '{$ref}' tidak ditemukan.");
    }

    private function previewRoomShow(array $parsed): array
    {
        $ref = $parsed['positional'][0] ?? throw new CommandException('Nomor room wajib: room show 01.');
        $room = $this->findRoom($ref);
        $room->load(['agents.role', 'projects']);

        $lines = ["Room-{$room->number} '{$room->name}' [{$room->status->value}]"];
        foreach ($room->agents as $agent) {
            $lines[] = "  - {$agent->slug} ({$agent->role->name}, {$agent->combo_name}, {$agent->status->value})";
        }
        $lines[] = '  Proyek: '.$room->projects->count();

        return [
            'ok' => true,
            'message' => implode(PHP_EOL, $lines),
            'data' => [
                'number' => $room->number,
                'name' => $room->name,
                'status' => $room->status->value,
                'agents' => $room->agents->map(fn ($a) => [
                    'slug' => $a->slug, 'name' => $a->name,
                    'role' => $a->role->name, 'combo' => $a->combo_name,
                    'status' => $a->status->value,
                ])->all(),
                'projects' => $room->projects->count(),
            ],
        ];
    }

    private function previewRoomLink(array $parsed, bool $link): array
    {
        [$a, $b] = [$parsed['positional'][0] ?? null, $parsed['positional'][1] ?? null];

        if ($a === null || $b === null) {
            throw new CommandException('Dua nomor room wajib: room link 01 02.');
        }

        $roomA = $this->findRoom($a);
        $roomB = $this->findRoom($b);

        if ($roomA->id === $roomB->id) {
            throw new CommandException('Room tidak dapat dihubungkan dengan dirinya sendiri.');
        }

        $verb = $link ? 'menghubungkan' : 'memutus';

        return [
            'ok' => true,
            'message' => "Akan {$verb} komunikasi Room-{$roomA->number} ↔ Room-{$roomB->number}.",
            'data' => ['a' => $roomA->number, 'b' => $roomB->number],
        ];
    }

    private function runRoomLink(array $parsed, bool $link): array
    {
        $preview = $this->previewRoomLink($parsed, $link);
        $roomA = $this->findRoom($preview['data']['a']);
        $roomB = $this->findRoom($preview['data']['b']);

        foreach ([[$roomA, $roomB], [$roomB, $roomA]] as [$room, $other]) {
            $settings = $room->settings ?? [];
            $links = $settings['links'] ?? [];

            if ($link) {
                $links[] = $other->number;
                $settings['inter_room_enabled'] = true;
            } else {
                $links = array_values(array_diff($links, [$other->number]));
                $settings['inter_room_enabled'] = $links !== [];
            }

            $settings['links'] = array_values(array_unique($links));
            $room->settings = $settings;
            $room->save();
        }

        $verb = $link ? 'dihubungkan' : 'diputus';

        return [
            'ok' => true,
            'message' => "Komunikasi Room-{$roomA->number} ↔ Room-{$roomB->number} {$verb}.",
            'data' => $preview['data'],
        ];
    }

    private function previewRoomArchive(array $parsed): array
    {
        $ref = $parsed['positional'][0] ?? throw new CommandException('Nomor room wajib: room archive 01.');
        $room = $this->findRoom($ref);

        return [
            'ok' => true,
            'message' => "Akan mengarsipkan Room-{$room->number} '{$room->name}' ({$room->agents()->count()} agen, {$room->projects()->count()} proyek).",
            'data' => ['number' => $room->number],
        ];
    }

    private function runRoomArchive(array $parsed): array
    {
        $preview = $this->previewRoomArchive($parsed);
        $room = $this->findRoom($preview['data']['number']);
        $room->status = RoomStatus::Archived;
        $room->save();

        return [
            'ok' => true,
            'message' => "Room-{$room->number} diarsipkan.",
            'data' => $preview['data'],
        ];
    }

    // ---- agent ----

    private function findRole(string $ref): Role
    {
        return Role::where('slug', $ref)->orWhere('name', $ref)->first()
            ?? throw new CommandException("Role '{$ref}' tidak ditemukan.");
    }

    private function findAgent(string $slug, ?string $roomRef = null): Agent
    {
        $query = Agent::where('slug', $slug);

        if ($roomRef !== null) {
            $query->whereBelongsTo($this->findRoom($roomRef), 'room');
        }

        return $query->first()
            ?? throw new CommandException("Agen '{$slug}' tidak ditemukan.");
    }

    private function nextAgentSlug(Room $room, string $base): string
    {
        $n = Agent::withTrashed()->where('room_id', $room->id)
            ->where('slug', 'like', "{$base}-%")->count() + 1;

        while (Agent::withTrashed()->where('room_id', $room->id)->where('slug', "{$base}-{$n}")->exists()) {
            $n++;
        }

        return "{$base}-{$n}";
    }

    private function previewAgentAdd(array $parsed): array
    {
        $options = $parsed['options'];
        $roomRef = (string) ($options['room'] ?? throw new CommandException("Room wajib: agent add --room 01 --role 'Backend Dev'."));
        $roleRef = (string) ($options['role'] ?? throw new CommandException("Role wajib: agent add --room 01 --role 'Backend Dev'."));

        $room = $this->findRoom($roomRef);
        $role = $this->findRole($roleRef);
        $combo = $options['model'] ?? $options['combo'] ?? $role->default_combo ?? config('aios.nine_router.default_combo');

        if (isset($options['name'])) {
            $name = (string) $options['name'];
            $slug = Str::slug($name);
            if (Agent::withTrashed()->where('room_id', $room->id)->where('slug', $slug)->exists()) {
                throw new CommandException("Agen '{$slug}' sudah ada di Room-{$room->number}.");
            }
        } else {
            $slug = $this->nextAgentSlug($room, $role->slug);
            $n = (int) substr($slug, strrpos($slug, '-') + 1);
            $name = "{$role->name} {$n}";
        }

        return [
            'ok' => true,
            'message' => "Akan menambah agen '{$slug}' (role {$role->name}, {$combo}) ke Room-{$room->number}.",
            'data' => [
                'room' => $room->number, 'role' => $role->slug,
                'slug' => $slug, 'name' => $name, 'combo' => $combo,
            ],
        ];
    }

    private function runAgentAdd(array $parsed): array
    {
        $preview = $this->previewAgentAdd($parsed);
        $data = $preview['data'];
        $room = $this->findRoom($data['room']);
        $role = $this->findRole($data['role']);

        $agent = Agent::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'room_id' => $room->id,
            'role_id' => $role->id,
            'combo_name' => $data['combo'],
            'base_url' => $parsed['options']['base-url'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => "Agen '{$agent->slug}' ditambah ke Room-{$room->number}, ikut alur via output role {$role->name}.",
            'data' => ['slug' => $agent->slug, 'room' => $room->number],
        ];
    }

    private function agentSetChanges(array $parsed): array
    {
        $slug = $parsed['positional'][0] ?? throw new CommandException('Slug agen wajib: agent set backend-dev-1 --model combo-hemat.');
        $options = $parsed['options'];

        if (($options['room'] ?? null) !== null) {
            $agent = $this->findAgent($slug, (string) $options['room']);
        } else {
            $agent = $this->findAgent($slug);
        }

        $changes = [];

        if (isset($options['model']) || isset($options['combo'])) {
            $changes['combo_name'] = (string) ($options['model'] ?? $options['combo']);
        }
        if (isset($options['role'])) {
            $changes['role_id'] = $this->findRole((string) $options['role'])->id;
        }
        if (isset($options['status'])) {
            $status = AgentStatus::tryFrom((string) $options['status'])
                ?? throw new CommandException("Status '{$options['status']}' tidak dikenal (active|disabled).");
            $changes['status'] = $status;
        }
        if (isset($options['name'])) {
            $changes['name'] = (string) $options['name'];
        }

        if ($changes === []) {
            throw new CommandException('Tidak ada perubahan. Contoh: agent set backend-dev-1 --model combo-hemat.');
        }

        return [$agent, $changes];
    }

    private function previewAgentSet(array $parsed): array
    {
        [$agent, $changes] = $this->agentSetChanges($parsed);
        $parts = [];

        foreach ($changes as $key => $value) {
            $parts[] = "{$key}=".(is_object($value) ? $value->value : $value);
        }

        return [
            'ok' => true,
            'message' => "Akan mengubah agen '{$agent->slug}': ".implode(', ', $parts).'.',
            'data' => ['slug' => $agent->slug, 'changes' => array_map(fn ($v) => is_object($v) ? $v->value : $v, $changes)],
        ];
    }

    private function runAgentSet(array $parsed): array
    {
        [$agent, $changes] = $this->agentSetChanges($parsed);
        $agent->fill($changes);
        $agent->save();

        return [
            'ok' => true,
            'message' => "Agen '{$agent->slug}' diubah tanpa mengganggu proyek berjalan.",
            'data' => ['slug' => $agent->slug],
        ];
    }

    private function previewAgentRemove(array $parsed): array
    {
        $slug = $parsed['positional'][0] ?? throw new CommandException('Slug agen wajib: agent remove backend-dev-1.');
        $options = $parsed['options'];
        $agent = isset($options['room']) ? $this->findAgent($slug, (string) $options['room']) : $this->findAgent($slug);

        return [
            'ok' => true,
            'message' => "Akan menonaktifkan agen '{$agent->slug}' (soft delete, riwayat proyek tetap utuh).",
            'data' => ['slug' => $agent->slug],
        ];
    }

    private function runAgentRemove(array $parsed): array
    {
        $preview = $this->previewAgentRemove($parsed);
        $agent = $this->findAgent($preview['data']['slug']);
        $agent->delete();

        return [
            'ok' => true,
            'message' => "Agen '{$agent->slug}' dinonaktifkan.",
            'data' => $preview['data'],
        ];
    }

    private function previewAgentShow(array $parsed): array
    {
        $slug = $parsed['positional'][0] ?? throw new CommandException('Slug agen wajib: agent show backend-dev-1.');
        $options = $parsed['options'];
        $agent = isset($options['room']) ? $this->findAgent($slug, (string) $options['room']) : $this->findAgent($slug);
        $agent->loadMissing('role', 'room');

        $tasks = Task::with('project')->whereBelongsTo($agent, 'agent')->latest()->limit(20)->get();
        $total = Task::whereBelongsTo($agent, 'agent')->count();
        $done = Task::whereBelongsTo($agent, 'agent')->where('status', TaskStatus::Done)->count();
        $tokens = Task::whereBelongsTo($agent, 'agent')->sum('tokens_in') + Task::whereBelongsTo($agent, 'agent')->sum('tokens_out');
        $percent = $total > 0 ? (int) round($done / $total * 100) : 0;

        $lines = ["Agen '{$agent->slug}' ({$agent->role->name}, Room-{$agent->room->number}, {$agent->status->value})"];
        $lines[] = "Progres: {$done}/{$total} tahap ({$percent}%), {$tokens} token.";

        foreach ($tasks as $task) {
            $lines[] = "  [{$task->status->value}] #{$task->id} {$task->project->slug}/{$task->stage}: {$task->title}";
        }

        return [
            'ok' => true,
            'message' => implode(PHP_EOL, $lines),
            'data' => ['slug' => $agent->slug, 'done' => $done, 'total' => $total, 'percent' => $percent, 'tokens' => $tokens],
        ];
    }

    private function findProject(string $ref): Project
    {
        return Project::where('slug', $ref)->orWhere('name', $ref)->first()
            ?? throw new CommandException("Proyek '{$ref}' tidak ditemukan.");
    }

    private function previewProjectProgress(array $parsed): array
    {
        $ref = $parsed['positional'][0] ?? throw new CommandException('Slug proyek wajib: project progress nama-proyek.');
        $project = $this->findProject($ref);
        $progress = app(Orchestrator::class)->progress($project);

        $lines = ["Proyek '{$project->name}': {$progress['done']}/{$progress['total']} tahap ({$progress['percent']}%)."];

        foreach ($progress['agents'] as $row) {
            $lines[] = "  {$row['slug']}: {$row['done']}/{$row['total']} ({$row['percent']}%), {$row['tokens']} token.";
        }

        return ['ok' => true, 'message' => implode(PHP_EOL, $lines), 'data' => $progress];
    }

    private function previewTaskRevise(array $parsed): array
    {
        [$task, $note] = $this->taskReviseInput($parsed);

        return [
            'ok' => true,
            'message' => "Akan merevisi tahap #{$task->id} '{$task->title}' ({$task->status->value}): {$note}",
            'data' => ['id' => $task->id, 'note' => $note],
        ];
    }

    private function runTaskRevise(array $parsed): array
    {
        [$task, $note] = $this->taskReviseInput($parsed);
        app(Orchestrator::class)->revise($task, $note);

        return [
            'ok' => true,
            'message' => "Tahap #{$task->id} diantre ulang dengan catatan revisi; versi artefak naik saat selesai.",
            'data' => ['id' => $task->id],
        ];
    }

    /** @return array{Task, string} */
    private function taskReviseInput(array $parsed): array
    {
        $id = $parsed['positional'][0] ?? throw new CommandException("ID tugas wajib: task revise 12 'catatan revisi'.");
        $note = trim($parsed['positional'][1] ?? '');

        if ($note === '') {
            throw new CommandException("Catatan revisi wajib: task revise {$id} 'catatan revisi'.");
        }

        $task = Task::find($id) ?? throw new CommandException("Tugas #{$id} tidak ditemukan.");

        return [$task, $note];
    }

    // ---- role ----

    /** @return list<string>|null */
    private function csvOption(array $options, string $key): ?array
    {
        if (! isset($options[$key])) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $options[$key]))));
    }

    private function previewRoleAdd(array $parsed): array
    {
        $name = trim($parsed['positional'][0] ?? '');

        if ($name === '') {
            throw new CommandException("Nama role wajib: role add 'Security Specialist'.");
        }

        $slug = Str::slug($name);

        if (Role::where('slug', $slug)->exists()) {
            throw new CommandException("Role '{$slug}' sudah ada.");
        }

        return [
            'ok' => true,
            'message' => "Akan membuat role '{$name}' ({$slug}).",
            'data' => ['slug' => $slug, 'name' => $name],
        ];
    }

    private function runRoleAdd(array $parsed): array
    {
        $preview = $this->previewRoleAdd($parsed);
        $options = $parsed['options'];

        $role = Role::create([
            'slug' => $preview['data']['slug'],
            'name' => $preview['data']['name'],
            'desc' => $options['desc'] ?? null,
            'instructions' => $options['instructions'] ?? "Role {$preview['data']['name']}: kerjakan sesuai input yang diterima dan hasilkan output yang dideklarasikan.",
            'allowed_tools' => $this->csvOption($options, 'tools') ?? ['file'],
            'expected_inputs' => $this->csvOption($options, 'inputs'),
            'outputs' => $this->csvOption($options, 'outputs'),
            'default_combo' => $options['combo'] ?? $options['model'] ?? null,
        ]);

        return [
            'ok' => true,
            'message' => "Role '{$role->slug}' dibuat, otomatis ikut serah terima via input/output yang dideklarasikan.",
            'data' => ['slug' => $role->slug],
        ];
    }

    // ---- project ----

    private function previewProjectRun(array $parsed): array
    {
        $options = $parsed['options'];
        $roomRef = (string) ($options['room'] ?? throw new CommandException("Room wajib: project run --room 01 'Judul proyek'."));
        $title = trim($parsed['positional'][0] ?? '');

        if ($title === '') {
            throw new CommandException("Judul proyek wajib: project run --room 01 'Judul proyek'.");
        }

        $room = $this->findRoom($roomRef);

        return [
            'ok' => true,
            'message' => "Akan menjalankan proyek '{$title}' di Room-{$room->number}.",
            'data' => ['room' => $room->number, 'title' => $title],
        ];
    }

    private function runProjectRun(array $parsed): array
    {
        $preview = $this->previewProjectRun($parsed);
        $room = $this->findRoom($preview['data']['room']);
        $title = $preview['data']['title'];

        $slug = Str::slug($title);
        $suffix = 2;
        while (Project::where('slug', $slug)->exists()) {
            $slug = Str::slug($title).'-'.$suffix++;
        }

        $project = Project::create([
            'slug' => $slug,
            'name' => $title,
            'idea' => $parsed['options']['idea'] ?? $title,
            'room_id' => $room->id,
            'repo_path' => config('aios.projects_root').'/'.$slug,
            'token_budget' => config('aios.task_token_budget'),
            'delegation' => config('aios.delegation'),
        ]);

        $plan = app(Orchestrator::class)->plan($project->fresh('room'));
        app(Orchestrator::class)->start($project);

        $suffix = $plan['unassigned'] === []
            ? ''
            : ' Tahap tanpa agen (tambah agen dulu, lalu ulangi): '.implode(', ', $plan['unassigned']).'.';

        return [
            'ok' => true,
            'message' => "Proyek '{$project->name}' berjalan di Room-{$room->number}: {$plan['planned']} tahap dikirim ke agen.{$suffix}",
            'data' => ['slug' => $project->slug, 'room' => $room->number],
        ];
    }

    // ---- bookkeeping ----

    /** @return array{ok: bool, message: string, data: array} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'data' => []];
    }

    private function recordHistory(array $parsed, string $status, array $result, ?int $userId): void
    {
        CommandHistory::create([
            'user_id' => $userId,
            'raw_input' => $parsed['raw'] ?? '',
            'parsed' => [
                'type' => $parsed['type'] ?? 'command',
                'command' => $parsed['command'] ?? null,
                'positional' => $parsed['positional'] ?? [],
                'options' => $parsed['options'] ?? [],
            ],
            'status' => $status,
            'result' => ['ok' => $result['ok'], 'message' => $result['message']],
        ]);
    }

    private function recordAudit(array $parsed, array $result, ?int $userId): void
    {
        AuditLog::create([
            'actor_type' => $userId === null ? 'console' : 'user',
            'actor_id' => $userId,
            'action' => 'command.'.($parsed['command'] ?? 'unknown'),
            'target_type' => $parsed['domain'] ?? null,
            'target_id' => $result['data']['id'] ?? null,
            'details' => ['raw' => $parsed['raw'] ?? '', 'data' => $result['data']],
        ]);
    }
}
