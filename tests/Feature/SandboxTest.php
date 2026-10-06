<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Room;
use App\Services\Aios\GitService;
use App\Services\Aios\SandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ManagesTestRepos;
use Tests\TestCase;

class SandboxTest extends TestCase
{
    use ManagesTestRepos;
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = $this->repoRoot();
        config(['aios.projects_root' => $this->root]);
    }

    protected function tearDown(): void
    {
        $this->clearRepoRoot($this->root);
        parent::tearDown();
    }

    public function test_init_commit_and_log(): void
    {
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'slug' => 'demo']);
        $git = app(GitService::class);

        $path = $git->init($project);
        $this->assertDirectoryExists($path.'/.git');

        $task = $project->tasks()->create([
            'stage' => 'prd', 'title' => 'PRD', 'step' => 3,
        ]);
        $artifact = $project->artifacts()->create([
            'room_id' => $project->room_id, 'type' => 'prd', 'title' => 'PRD', 'body' => '# PRD',
        ]);

        $sha = $git->commitTask($task, $artifact);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $sha);
        $this->assertSame('# PRD', file_get_contents($path.'/artefak/prd-v1.md'));
        $this->assertNotEmpty($git->log($project));
    }

    public function test_verify_passes_on_clean_repo(): void
    {
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'slug' => 'demo']);
        $git = app(GitService::class);
        $git->init($project);

        $task = $project->tasks()->create(['stage' => 'prd', 'title' => 'PRD', 'step' => 3]);
        $artifact = $project->artifacts()->create([
            'room_id' => $project->room_id, 'type' => 'prd', 'title' => 'PRD', 'body' => '# PRD',
        ]);
        $git->commitTask($task, $artifact);

        $report = app(SandboxService::class)->verify($project);

        $this->assertTrue($report['ok'], json_encode($report['checks']));
        $this->assertArrayHasKey('artefak', $report['checks']);
    }

    public function test_empty_env_falls_back_to_storage_path(): void
    {
        config(['aios.projects_root' => '']);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'slug' => 'demo', 'repo_path' => null]);

        $path = app(GitService::class)->repoPath($project);

        $this->assertSame(storage_path('app/projects').'/demo', $path);
    }

    public function test_verify_fails_when_disk_differs_from_db(): void
    {
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'slug' => 'demo']);
        $git = app(GitService::class);
        $path = $git->init($project);

        $artifact = $project->artifacts()->create([
            'room_id' => $project->room_id, 'type' => 'prd', 'title' => 'PRD', 'body' => '# PRD',
        ]);
        file_put_contents($path.'/artefak/prd-v1.md', '# BERUBAH MANUAL');

        $report = app(SandboxService::class)->verify($project);

        $this->assertFalse($report['ok']);
    }
}
