<?php

namespace App\Jobs;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Artifact;
use App\Models\Task;
use App\Services\Aios\CommandException;
use App\Services\Aios\GitService;
use App\Services\Aios\Orchestrator;
use App\Services\Aios\SandboxService;
use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan satu tahap proyek atas nama agen.
 *
 * Percobaan dihitung manual di tasks.attempts (maks 3) agar kegagalan
 * model tercatat di linimasa, bukan sekadar retry antrean. Pemeriksaan
 * status proyek membuat jeda/henti dan kelanjutan setelah putus berperilaku
 * benar: job yang telanjur antre keluar tanpa efek saat proyek tak running.
 */
class RunAgentTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Task $task) {}

    public function handle(Orchestrator $orchestrator, NineRouterClient $models, SettingsService $settings, GitService $git, SandboxService $sandbox): void
    {
        $task = Task::with(['project', 'agent.role'])->find($this->task->id);

        if ($task === null || ! in_array($task->status, [TaskStatus::Queued, TaskStatus::Running], true)) {
            return;
        }

        if ($task->project->status !== ProjectStatus::Running) {
            return;
        }

        if ($task->agent === null || $task->agent->trashed() || $task->agent->status->value !== 'active') {
            $this->markFailed($task, 'Agen tidak aktif.');

            return;
        }

        $spent = $task->tokens_in + $task->tokens_out;
        $budget = $settings->getInt('aios.task_token_budget', (int) config('aios.task_token_budget'));

        if ($spent >= $budget) {
            $task->status = TaskStatus::Paused;
            $task->error = "Anggaran {$budget} token tercapai; tugas dijeda.";
            $task->save();

            return;
        }

        $projectBudget = $task->project->token_budget;

        if ($projectBudget !== null && $task->project->costs()->sum('tokens_in') + $task->project->costs()->sum('tokens_out') >= $projectBudget) {
            $task->project->status = ProjectStatus::Paused;
            $task->project->save();
            $task->status = TaskStatus::Paused;
            $task->error = "Anggaran proyek {$projectBudget} token tercapai; proyek dijeda.";
            $task->save();

            return;
        }

        $task->status = TaskStatus::Running;
        $task->started_at ??= now();
        $task->attempts++;
        $task->save();

        try {
            $result = $models->chatForAgent(
                $task->agent,
                $this->messages($task),
                ['project_id' => $task->project_id, 'stage' => $task->stage],
            );
        } catch (NineRouterException $e) {
            if ($task->attempts < 3) {
                $task->status = TaskStatus::Queued;
                $task->error = "Percobaan {$task->attempts} gagal: {$e->getMessage()}";
                $task->save();
                self::dispatch($task)->delay(now()->addMinutes($task->attempts));

                return;
            }

            $this->markFailed($task, "Gagal 3 kali: {$e->getMessage()}");

            return;
        }

        DB::transaction(function () use ($task, $result, &$artifact) {
            $version = Artifact::where('project_id', $task->project_id)
                ->where('type', $task->stage)
                ->max('version') ?? 0;

            $artifact = Artifact::create([
                'project_id' => $task->project_id,
                'room_id' => $task->project->room_id,
                'type' => $task->stage,
                'title' => $task->title,
                'version' => $version + 1,
                'body' => $result['content'],
                'author_agent_id' => $task->agent_id,
                'meta' => ['model' => $result['model'], 'latency_ms' => $result['latency_ms']],
            ]);

            foreach ($task->inputs ?? [] as $inputId) {
                $task->project->artifacts()->whereKey($inputId)->exists() && $artifact->outgoingLinks()->create([
                    'from_artifact_id' => $artifact->id,
                    'to_artifact_id' => $inputId,
                    'relation' => 'derived_from',
                ]);
            }

            $task->output_artifact_id = $artifact->id;
            $task->tokens_in += $result['tokens_in'];
            $task->tokens_out += $result['tokens_out'];
            $task->status = TaskStatus::Done;
            $task->completed_at = now();
            $task->error = null;
            $task->save();
        });

        $task->refresh();

        try {
            $sha = $git->commitTask($task, $artifact);
        } catch (CommandException $e) {
            $this->markFailed($task, 'Git: '.$e->getMessage());

            return;
        }

        $meta = $artifact->meta ?? [];
        $meta['commit'] = $sha;
        $artifact->meta = $meta;
        $artifact->save();

        // QA (FR-17): verifikasi sandbox atas repo; gagal → kembali ke pembuat.
        if ($task->stage === 'test-report') {
            $report = $sandbox->verify($task->project);
            $meta['sandbox'] = $report;
            $artifact->meta = $meta;
            $artifact->save();

            if (! $report['ok']) {
                $this->markFailed($task, 'Verifikasi sandbox gagal: '.implode('; ', $report['checks']));

                return;
            }
        }

        $orchestrator->onTaskDone($task);
    }

    /** @return list<array{role: string, content: string}> */
    private function messages(Task $task): array
    {
        $role = $task->agent->role;
        $inputs = $task->project->artifacts()->whereKey($task->inputs ?? [])->get();

        $context = $inputs->map(
            fn (Artifact $a) => "### {$a->type} v{$a->version}: {$a->title}\n".mb_substr($a->body ?? '', 0, 8000)
        )->implode("\n\n");

        return [
            [
                'role' => 'system',
                'content' => "Kamu {$role->name} di studio pengembangan perangkat lunak.\nInstruksi: {$role->instructions}\nHasilkan HANYA isi artefak bertipe '{$task->stage}'. Tanpa penjelasan di luar artefak.",
            ],
            [
                'role' => 'user',
                'content' => "Ide proyek: {$task->project->idea}\nTahap: {$task->title}\n\nBahan serah terima:\n{$context}",
            ],
        ];
    }

    private function markFailed(Task $task, string $error): void
    {
        $task->status = TaskStatus::Failed;
        $task->error = $error;
        $task->completed_at = now();
        $task->save();
    }
}
