<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Approval;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use App\Services\Aios\CommandException;
use App\Services\Aios\GitService;
use App\Services\Aios\Orchestrator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectsController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $user = auth()->user();

        $projects = Project::with('room')->withCount('tasks')
            ->when(! in_array($user->role, [UserRole::Owner, UserRole::Manager], true), function ($query) use ($user) {
                $query->whereIn('id', $user->memberProjects()->select('projects.id'));
            })
            ->latest()
            ->get();

        return view('projects.index', [
            'projects' => $projects,
        ]);
    }

    public function show(Project $project, Orchestrator $orchestrator): View
    {
        $this->authorize('view', $project);
        $project->load(['room', 'tasks.agent', 'tasks.output', 'approvals.requester', 'members.user', 'revisions.task', 'revisions.user']);

        $done = $project->tasks->where('status', TaskStatus::Done)->count();
        $total = max($project->tasks->count(), 1);
        $spent = $project->costs()->sum('tokens_in') + $project->costs()->sum('tokens_out');

        $types = $project->artifacts()->select('type')->distinct()->orderBy('type')->pluck('type')->all();
        $previewType = request('preview');
        $preferred = ['wireframe', 'ux-flow', 'frontend-code', 'prd'];

        if (! in_array($previewType, $types, true)) {
            $previewType = null;

            foreach ([...$preferred, ...$types] as $candidate) {
                if (in_array($candidate, $types, true)) {
                    $previewType = $candidate;
                    break;
                }
            }
        }

        $previewArtifact = $previewType !== null
            ? $project->artifacts()->where('type', $previewType)->latest('version')->first()
            : null;

        return view('projects.show', [
            'project' => $project,
            'progress' => (int) round($done / $total * 100),
            'pendingApprovals' => $project->approvals()->pending()->latest()->get(),
            'pipeline' => $orchestrator->pipelineFor($project),
            'customPipeline' => $project->stages !== null,
            'tokensSpent' => $spent,
            'agentProgress' => $orchestrator->progress($project),
            'previewTypes' => $types,
            'previewType' => $previewType,
            'previewHtml' => $previewArtifact !== null ? (string) (new CommonMarkConverter)->convert($previewArtifact->body ?? '') : null,
            'previewVersion' => $previewArtifact?->version,
        ]);
    }

    public function approve(Request $request, Project $project, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($approval->project_id === $project->id, 404);
        $this->authorize('view', $project);

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $orchestrator->approve($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$approval->stage} disetujui, alur lanjut.");
    }

    public function reject(Request $request, Project $project, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($approval->project_id === $project->id, 404);
        $this->authorize('view', $project);

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $orchestrator->reject($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$approval->stage} ditolak, proyek dijeda.");
    }

    public function pause(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);
        $orchestrator->pause($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dijeda.');
    }

    public function resume(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);
        $orchestrator->resume($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dilanjutkan.');
    }

    public function cancel(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);
        $orchestrator->cancel($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dihentikan.');
    }

    public function retry(Project $project, Task $task, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('manage', $project);
        $orchestrator->retry($task);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$task->title} diantre ulang.");
    }

    public function download(Project $project, GitService $git): BinaryFileResponse
    {
        $this->authorize('manage', $project);
        abort_unless(is_dir($git->repoPath($project).'/.git'), 404, 'Repositori proyek belum ada.');

        $zipPath = $git->archive($project);

        return response()->download($zipPath, $project->slug.'.zip')->deleteFileAfterSend(true);
    }

    public function revise(Request $request, Project $project, Task $task, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        $this->authorize('manage', $project);
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $orchestrator->revise($task, $validated['note'], (int) auth()->id());
        } catch (CommandException $e) {
            return back()->withErrors(['revision' => $e->getMessage()]);
        }

        $orchestrator->resume($project);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$task->title} direvisi dan diantre ulang.");
    }

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string'],
        ]);

        $role = UserRole::tryFrom($validated['role']);

        if ($role === null) {
            return back()->withErrors(['role' => 'Peran tidak dikenal.']);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();

        ProjectMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $user->id],
            ['role' => $role]
        );

        return redirect()->route('projects.show', $project)->with('status', "{$user->email} ditambah sebagai {$role->value}.");
    }

    public function destroyMember(Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manage', $project);
        abort_unless($member->project_id === $project->id, 404);
        $member->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Akses anggota dicabut.');
    }

    public function moveStage(Project $project, string $stage, string $direction, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);

        try {
            $orchestrator->moveStage($project, $stage, $direction === 'down' ? 1 : -1);
        } catch (CommandException $e) {
            return back()->withErrors(['stages' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Urutan tahap diubah.');
    }

    public function removeStage(Project $project, string $stage, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);

        try {
            $orchestrator->removeStage($project, $stage);
        } catch (CommandException $e) {
            return back()->withErrors(['stages' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$stage} dihapus dari alur.");
    }

    public function resetStages(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('manage', $project);

        try {
            $orchestrator->resetStages($project);
        } catch (CommandException $e) {
            return back()->withErrors(['stages' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('status', 'Alur dikembalikan ke bawaan.');
    }
}
