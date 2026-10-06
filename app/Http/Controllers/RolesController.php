<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Role;
use App\Services\Aios\CommandExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount('agents')->orderBy('name')->get(),
            'agents' => Agent::with(['room', 'role'])->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request, CommandExecutor $executor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string', 'max:1000'],
            'tools' => ['nullable', 'string', 'max:255'],
            'inputs' => ['nullable', 'string', 'max:255'],
            'outputs' => ['nullable', 'string', 'max:255'],
            'combo' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $executor->run([
            'type' => 'command',
            'domain' => 'role',
            'action' => 'add',
            'command' => 'role.add',
            'positional' => [$validated['name']],
            'options' => array_filter([
                'desc' => $validated['desc'] ?? null,
                'tools' => $validated['tools'] ?? null,
                'inputs' => $validated['inputs'] ?? null,
                'outputs' => $validated['outputs'] ?? null,
                'combo' => $validated['combo'] ?? null,
            ]),
            'raw' => "role add '{$validated['name']}'",
        ]);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['name' => $result['message']]);
        }

        return redirect()->route('roles.index')->with('status', $result['message']);
    }
}
