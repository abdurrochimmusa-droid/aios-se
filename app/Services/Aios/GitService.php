<?php

namespace App\Services\Aios;

use App\Models\Artifact;
use App\Models\Project;
use App\Models\Task;
use App\Services\SettingsService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Repositori Git per proyek (PRD FR-16).
 *
 * Satu repo di {projects_root}/{slug}: folder artefak/ berisi hasil tiap
 * tahap, satu commit per tugas selesai. Dipakai linimasa, audit, dan
 * pengiriman ke sandbox (Fase 2: driver lokal; CT Proxmox via git clone).
 */
class GitService
{
    public function repoPath(Project $project): string
    {
        $settings = app(SettingsService::class);

        $root = $project->repo_path
            ?: $settings->get('aios.projects_root', (string) config('aios.projects_root'))
            ?: storage_path('app/projects');

        return rtrim($root, '/\\').'/'.$project->slug;
    }

    public function init(Project $project): string
    {
        $path = $this->repoPath($project);

        if (! is_dir($path.'/.git')) {
            File::ensureDirectoryExists($path.'/artefak');
            $this->run($path, ['git', 'init', '-q']);
            $this->run($path, ['git', 'config', 'user.name', 'aios-se']);
            $this->run($path, ['git', 'config', 'user.email', 'aios@localhost']);
            File::put($path.'/.gitignore', "vendor/\nnode_modules/\n.env\n");
            $this->run($path, ['git', 'add', '-A']);
            $this->run($path, ['git', 'commit', '-qm', 'init proyek '.$project->slug]);
        }

        return $path;
    }

    /**
     * Tulis artefak ke artefak/ dan commit. Mengembalikan SHA commit.
     */
    public function commitTask(Task $task, Artifact $artifact): string
    {
        $project = $task->project;
        $path = $this->init($project);

        $filename = $this->filename($artifact);
        File::put($path.'/artefak/'.$filename, $artifact->body ?? '');

        $this->run($path, ['git', 'add', '-A']);

        // Idempoten: bila tak ada perubahan, pakai HEAD berjalan.
        $dirty = trim($this->run($path, ['git', 'status', '--porcelain']));

        if ($dirty !== '') {
            $this->run($path, ['git', 'commit', '-qm', "[{$task->stage}] {$task->title} (v{$artifact->version})"]);
        }

        return trim($this->run($path, ['git', 'rev-parse', 'HEAD']));
    }

    /** @return list<string> */
    public function log(Project $project, int $limit = 20): array
    {
        $path = $this->repoPath($project);

        if (! is_dir($path.'/.git')) {
            return [];
        }

        $out = $this->run($path, ['git', 'log', '--oneline', '-n', (string) $limit]);

        return array_values(array_filter(explode("\n", trim($out))));
    }

    public function filename(Artifact $artifact): string
    {
        $safe = str($artifact->type)->slug('_')->toString();

        return "{$safe}-v{$artifact->version}.md";
    }

    private function run(string $path, array $command): string
    {
        $process = new Process($command, $path);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new CommandException('Git gagal: '.mb_substr($process->getErrorOutput(), 0, 300));
        }

        return $process->getOutput();
    }
}
