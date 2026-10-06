<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\Approval;
use App\Services\Aios\Orchestrator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalsController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $user = auth()->user();
        $memberIds = $user->memberProjects()->select('projects.id');

        $scope = Approval::with(['project.room', 'artifact', 'requester'])
            ->when(! in_array($user->role, [UserRole::Owner, UserRole::Manager], true), function ($query) use ($memberIds) {
                $query->whereIn('project_id', $memberIds);
            });

        return view('approvals.index', [
            'pending' => (clone $scope)->where('status', ApprovalStatus::Pending)->latest()->get(),
            'recent' => (clone $scope)->whereNot('status', ApprovalStatus::Pending)
                ->latest('decided_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function approve(Request $request, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('view', $approval->project);

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $orchestrator->approve($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('approvals.index')->with('status', "Gerbang {$approval->stage} disetujui.");
    }

    public function reject(Request $request, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('view', $approval->project);

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $orchestrator->reject($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('approvals.index')->with('status', "Gerbang {$approval->stage} ditolak.");
    }
}
