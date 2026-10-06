<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Models\Approval;
use App\Models\Project;
use App\Models\Task;
use App\Services\Aios\Orchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectsController extends Controller
{
    public function index(): View
    {
        return view('projects.index', [
            'projects' => Project::with('room')->withCount('tasks')->latest()->get(),
        ]);
    }

    public function show(Project $project): View
    {
        $project->load(['room', 'tasks.agent', 'tasks.output', 'approvals.requester']);

        $done = $project->tasks->where('status', TaskStatus::Done)->count();
        $total = max($project->tasks->count(), 1);

        return view('projects.show', [
            'project' => $project,
            'progress' => (int) round($done / $total * 100),
            'pendingApprovals' => $project->approvals()->pending()->latest()->get(),
        ]);
    }

    public function approve(Request $request, Project $project, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($approval->project_id === $project->id, 404);

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

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $orchestrator->reject($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$approval->stage} ditolak, proyek dijeda.");
    }

    public function pause(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $orchestrator->pause($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dijeda.');
    }

    public function resume(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $orchestrator->resume($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dilanjutkan.');
    }

    public function cancel(Project $project, Orchestrator $orchestrator): RedirectResponse
    {
        $orchestrator->cancel($project);

        return redirect()->route('projects.show', $project)->with('status', 'Proyek dihentikan.');
    }

    public function retry(Project $project, Task $task, Orchestrator $orchestrator): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        $orchestrator->retry($task);

        return redirect()->route('projects.show', $project)->with('status', "Tahap {$task->title} diantre ulang.");
    }
}
