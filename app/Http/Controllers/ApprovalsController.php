<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Services\Aios\Orchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalsController extends Controller
{
    public function index(): View
    {
        return view('approvals.index', [
            'pending' => Approval::pending()->with(['project.room', 'artifact', 'requester'])->latest()->get(),
            'recent' => Approval::whereNot('status', ApprovalStatus::Pending)
                ->with(['project', 'decider'])
                ->latest('decided_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function approve(Request $request, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $orchestrator->approve($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('approvals.index')->with('status', "Gerbang {$approval->stage} disetujui.");
    }

    public function reject(Request $request, Approval $approval, Orchestrator $orchestrator): RedirectResponse
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Persetujuan ini sudah diputuskan.']);
        }

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $orchestrator->reject($approval, (int) auth()->id(), $validated['note'] ?? null);

        return redirect()->route('approvals.index')->with('status', "Gerbang {$approval->stage} ditolak.");
    }
}
