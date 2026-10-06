<?php

namespace App\Services\Aios;

use App\Enums\ApprovalStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunAgentTask;
use App\Models\Agent;
use App\Models\Approval;
use App\Models\Project;
use App\Models\Revision;
use App\Models\Task;
use App\Services\SettingsService;

/**
 * Orkestrator Fase 1 (PRD FR-11 s.d. FR-14, FR-24, FR-25).
 *
 * - plan(): pecah ide menjadi tahap pipeline, tugaskan ke agen yang role-nya
 *   menghasilkan output tahap itu. Paralelisme = entri se-step.
 * - start(): tandai proyek running, kirim step pertama ke antrean.
 * - onTaskDone(): buka gerbang persetujuan bila tahapnya bergate,
 *   gerbang rilis setelah QA, atau lanjutkan step berikut.
 * - delegate(): agen menugaskan langsung ke agen lain dengan batas lunak
 *   (kedalaman + putaran per pasangan, PRD FR-24).
 * - pause()/resume()/cancel(): tombol henti per proyek (PRD FR-25).
 */
class Orchestrator
{
    /**
     * Alur yang dipakai proyek: salinan kustom bila ada, else bawaan config.
     *
     * @return list<array{stage: string, title: string, step: int, gate: ?string, outputs: list<string>}>
     */
    public function pipelineFor(Project $project): array
    {
        return $project->stages ?? config('aios.pipeline');
    }

    /**
     * @return array{planned: int, unassigned: list<string>}
     */
    public function plan(Project $project): array
    {
        $project->refresh();

        if ($project->tasks()->exists()) {
            return ['planned' => 0, 'unassigned' => []];
        }

        $project->loadMissing('room.agents.role');
        $planned = 0;
        $unassigned = [];

        foreach ($this->pipelineFor($project) as $entry) {
            $agent = $this->matchAgent($project, $entry['outputs']);
            $inputs = $this->stageInputs($project, $entry['step']);

            Task::create([
                'project_id' => $project->id,
                'stage' => $entry['stage'],
                'title' => $entry['title'],
                'step' => $entry['step'],
                'agent_id' => $agent?->id,
                'inputs' => $inputs,
            ]);

            $planned++;

            if ($agent === null) {
                $unassigned[] = $entry['stage'];
            }
        }

        return ['planned' => $planned, 'unassigned' => $unassigned];
    }

    public function start(Project $project): void
    {
        $project->status = ProjectStatus::Running;
        $project->save();

        $this->dispatchNext($project);
    }

    public function dispatchNext(Project $project): void
    {
        $project->refresh();

        if ($project->status !== ProjectStatus::Running) {
            return;
        }

        $step = $project->tasks()
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled])
            ->min('step');

        if ($step === null) {
            return;
        }

        $project->tasks()
            ->where('step', $step)
            ->where('status', TaskStatus::Queued)
            ->whereNotNull('agent_id')
            ->each(fn (Task $task) => RunAgentTask::dispatch($task));
    }

    public function onTaskDone(Task $task): void
    {
        $task->refresh();
        $entry = $this->pipelineEntry($task->project, $task->stage);

        if ($entry !== null && ($entry['gate'] ?? null) !== null) {
            $task->status = TaskStatus::WaitingApproval;
            $task->save();

            Approval::create([
                'project_id' => $task->project_id,
                'stage' => $entry['gate'],
                'artifact_id' => $task->output_artifact_id,
                'requested_by' => $task->project->owner_id,
            ]);

            return;
        }

        if (($entry['release_after'] ?? false) === true) {
            Approval::create([
                'project_id' => $task->project_id,
                'stage' => 'release',
                'artifact_id' => $task->output_artifact_id,
                'requested_by' => $task->project->owner_id,
            ]);

            return;
        }

        $this->dispatchNext($task->project);
    }

    public function approve(Approval $approval, int $userId, ?string $note = null): void
    {
        $approval->status = ApprovalStatus::Approved;
        $approval->decided_by = $userId;
        $approval->note = $note;
        $approval->decided_at = now();
        $approval->save();

        $project = $approval->project;

        if ($approval->stage === 'release') {
            $project->status = ProjectStatus::Done;
            $project->save();

            return;
        }

        $task = $project->tasks()
            ->where('status', TaskStatus::WaitingApproval)
            ->where('output_artifact_id', $approval->artifact_id)
            ->first();

        if ($task !== null) {
            $task->status = TaskStatus::Done;
            $task->completed_at = now();
            $task->save();
        }

        $this->dispatchNext($project);
    }

    public function reject(Approval $approval, int $userId, ?string $note = null): void
    {
        $approval->status = ApprovalStatus::Rejected;
        $approval->decided_by = $userId;
        $approval->note = $note;
        $approval->decided_at = now();
        $approval->save();

        $project = $approval->project;
        $project->status = ProjectStatus::Paused;
        $project->save();

        $project->tasks()
            ->where('status', TaskStatus::WaitingApproval)
            ->where('output_artifact_id', $approval->artifact_id)
            ->update(['status' => TaskStatus::Failed->value, 'error' => $note ?? 'Ditolak penyetuju.']);
    }

    /**
     * Delegasi langsung antaragen dengan batas lunak (FR-24).
     *
     * @throws CommandException bila kedalaman atau putaran terlampaui.
     */
    public function delegate(Task $parent, Agent $to, string $title): Task
    {
        $parent->loadMissing('project');
        $maxDepth = app(SettingsService::class)->getInt('aios.delegation_max_depth', (int) config('aios.delegation.max_depth'));
        $maxRounds = app(SettingsService::class)->getInt('aios.delegation_max_rounds', (int) config('aios.delegation.max_rounds'));

        if ($parent->depth + 1 > $maxDepth) {
            throw new CommandException("Batas kedalaman delegasi ({$maxDepth}) tercapai. Lanjutkan manual atau hentikan.");
        }

        $rounds = $this->countRounds($parent, $to->id);

        if ($rounds >= $maxRounds) {
            throw new CommandException("Batas {$maxRounds} putaran antara pasangan agen ini tercapai. Lanjutkan manual atau hentikan.");
        }

        return Task::create([
            'project_id' => $parent->project_id,
            'stage' => $parent->stage,
            'title' => $title,
            'step' => $parent->step,
            'agent_id' => $to->id,
            'inputs' => $parent->inputs,
            'depth' => $parent->depth + 1,
            'parent_id' => $parent->id,
        ]);
    }

    public function pause(Project $project): void
    {
        $project->status = ProjectStatus::Paused;
        $project->save();
    }

    public function resume(Project $project): void
    {
        $project->status = ProjectStatus::Running;
        $project->save();

        $this->dispatchNext($project);
    }

    public function cancel(Project $project): void
    {
        $project->tasks()->active()->update(['status' => TaskStatus::Cancelled->value]);
        $project->status = ProjectStatus::Done;
        $project->save();
    }

    public function retry(Task $task): void
    {
        $task->status = TaskStatus::Queued;
        $task->error = null;
        $task->save();

        $this->dispatchNext($task->project);
    }

    /**
     * Perintah revisi manusia saat hasil tahap salah: tugas diantre ulang
     * dengan catatan yang dibaca agen. Versi artefak naik saat selesai.
     * Perintah dicatat di riwayat revisi proyek (tidak menambah proyek).
     */
    public function revise(Task $task, string $note, ?int $userId = null): void
    {
        $note = trim($note);

        if ($note === '') {
            throw new CommandException('Catatan revisi wajib diisi.');
        }

        if (! in_array($task->status, [TaskStatus::Failed, TaskStatus::Done, TaskStatus::Paused], true)) {
            throw new CommandException("Tahap '{$task->title}' berstatus {$task->status->value}; revisi hanya untuk yang gagal/selesai/dijeda.");
        }

        Revision::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $userId,
            'note' => $note,
            'artifact_version' => $task->output?->version,
        ]);

        $task->status = TaskStatus::Queued;
        $task->error = null;
        $task->revision_note = $note;
        $task->save();
    }

    /**
     * Progres pengerjaan: total proyek + per agen (selesai/total/persen).
     *
     * @return array{total: int, done: int, percent: int, agents: list<array{slug: string, name: string, total: int, done: int, percent: int, tokens: int}>}
     */
    public function progress(Project $project): array
    {
        $tasks = $project->tasks()->with('agent')->get();
        $total = $tasks->count();
        $done = $tasks->where('status', TaskStatus::Done)->count();

        $agents = $tasks->groupBy('agent_id')->map(function ($group) {
            $first = $group->first();
            $count = $group->count();
            $finished = $group->where('status', TaskStatus::Done)->count();

            return [
                'slug' => $first->agent?->slug ?? 'tanpa-agen',
                'name' => $first->agent?->name ?? 'Tanpa agen',
                'total' => $count,
                'done' => $finished,
                'percent' => $count > 0 ? (int) round($finished / $count * 100) : 0,
                'tokens' => $group->sum('tokens_in') + $group->sum('tokens_out'),
            ];
        })->values()->all();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'agents' => $agents,
        ];
    }

    /**
     * Ubah urutan/hapus tahap per proyek (FR-14). Hanya sebelum tahap
     * berjalan: alur yang sudah bergerak tidak bisa disusun ulang.
     *
     * @throws CommandException
     */
    public function editableStages(Project $project): array
    {
        if ($project->tasks()->whereNot('status', TaskStatus::Queued)->exists()) {
            throw new CommandException('Alur tak bisa diubah karena tahap sudah berjalan.');
        }

        if ($project->stages === null) {
            $project->stages = config('aios.pipeline');
            $project->save();
        }

        return $project->stages;
    }

    /** @throws CommandException */
    public function moveStage(Project $project, string $stage, int $direction): void
    {
        $stages = array_values($this->editableStages($project));
        $index = collect($stages)->search(fn ($entry) => $entry['stage'] === $stage);

        if ($index === false) {
            throw new CommandException("Tahap '{$stage}' tidak ada di alur proyek ini.");
        }

        $other = $index + $direction;

        if (! isset($stages[$other])) {
            return;
        }

        [$stages[$index], $stages[$other]] = [$stages[$other], $stages[$index]];
        $stages = $this->renumberSteps($stages);

        $project->stages = $stages;
        $project->save();
    }

    /** @throws CommandException */
    public function removeStage(Project $project, string $stage): void
    {
        $stages = array_values($this->editableStages($project));
        $kept = array_values(array_filter($stages, fn ($entry) => $entry['stage'] !== $stage));

        if (count($kept) === count($stages)) {
            throw new CommandException("Tahap '{$stage}' tidak ada di alur proyek ini.");
        }

        $project->stages = $this->renumberSteps($kept);
        $project->save();
    }

    public function resetStages(Project $project): void
    {
        if ($project->tasks()->whereNot('status', TaskStatus::Queued)->exists()) {
            throw new CommandException('Alur tak bisa diubah karena tahap sudah berjalan.');
        }

        $project->stages = null;
        $project->save();
    }

    /** @param list<array> $stages */
    private function renumberSteps(array $stages): array
    {
        $step = 0;
        $previous = null;

        foreach ($stages as $i => $entry) {
            if ($entry['step'] !== $previous) {
                $step++;
                $previous = $entry['step'];
            }

            $stages[$i]['step'] = $step;
        }

        return $stages;
    }

    private function matchAgent(Project $project, array $outputs): ?Agent
    {
        foreach ($project->room->agents as $agent) {
            if ($agent->trashed() || $agent->status->value !== 'active') {
                continue;
            }

            if (array_intersect($agent->role->outputs ?? [], $outputs) !== []) {
                return $agent;
            }
        }

        return null;
    }

    /** @return list<int> */
    private function stageInputs(Project $project, int $step): array
    {
        return $project->tasks()
            ->where('step', '<', $step)
            ->whereNotNull('output_artifact_id')
            ->orderBy('step')
            ->pluck('output_artifact_id')
            ->all();
    }

    private function pipelineEntry(Project $project, string $stage): ?array
    {
        foreach ($this->pipelineFor($project) as $entry) {
            if ($entry['stage'] === $stage) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Hitung berapa kali agen tujuan sudah dilibatkan di rantai delegasi ini.
     * Batas lunak: tiap putaran bolak-balik menambah satu; saat mencapai
     * maksimum, tugas dijeda dan manusia memutuskan.
     */
    private function countRounds(Task $task, int $toId): int
    {
        $rounds = 0;
        $current = $task;

        while ($current !== null) {
            if ($current->agent_id === $toId) {
                $rounds++;
            }

            $current = $current->parent;
        }

        return $rounds;
    }
}
