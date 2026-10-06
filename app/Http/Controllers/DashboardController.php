<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Approval;
use App\Models\Cost;
use App\Models\Project;
use App\Models\Room;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pendingApprovals = Approval::pending()
            ->with(['project.room', 'artifact', 'requester'])
            ->latest()
            ->limit(10)
            ->get();

        $todayCosts = Cost::whereDate('created_at', today());

        return view('dashboard', [
            'stats' => [
                'rooms' => Room::active()->count(),
                'agents' => Agent::active()->count(),
                'projects' => Project::running()->count(),
                'approvals' => Approval::pending()->count(),
            ],
            'pendingApprovals' => $pendingApprovals,
            'rooms' => Room::active()->withCount(['agents', 'projects'])->orderBy('number')->get(),
            'todayTokens' => (clone $todayCosts)->sum('tokens_in') + (clone $todayCosts)->sum('tokens_out'),
            'todayCalls' => (clone $todayCosts)->count(),
            'topAgents' => Cost::query()
                ->selectRaw('agent_id, SUM(tokens_in + tokens_out) as tokens')
                ->whereDate('created_at', today())
                ->whereNotNull('agent_id')
                ->groupBy('agent_id')
                ->orderByDesc('tokens')
                ->limit(5)
                ->with('agent')
                ->get(),
        ]);
    }
}
