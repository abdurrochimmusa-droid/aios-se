<?php

namespace App\Http\Controllers;

use App\Models\Cost;
use Illuminate\View\View;

class CostsController extends Controller
{
    public function index(): View
    {
        $today = Cost::whereDate('created_at', today());

        return view('costs.index', [
            'totalTokens' => (int) Cost::sum('tokens_in') + (int) Cost::sum('tokens_out'),
            'totalCalls' => Cost::count(),
            'todayTokens' => (int) (clone $today)->sum('tokens_in') + (int) (clone $today)->sum('tokens_out'),
            'todayCalls' => (clone $today)->count(),
            'byProject' => Cost::selectRaw('project_id, COUNT(*) as calls, SUM(tokens_in + tokens_out) as tokens')
                ->groupBy('project_id')->orderByDesc('tokens')->limit(20)->with('project')->get(),
            'byAgent' => Cost::selectRaw('agent_id, COUNT(*) as calls, SUM(tokens_in + tokens_out) as tokens')
                ->whereNotNull('agent_id')->groupBy('agent_id')->orderByDesc('tokens')->limit(20)->with('agent')->get(),
            'byModel' => Cost::selectRaw('model, COUNT(*) as calls, SUM(tokens_in + tokens_out) as tokens')
                ->groupBy('model')->orderByDesc('tokens')->get(),
        ]);
    }
}
