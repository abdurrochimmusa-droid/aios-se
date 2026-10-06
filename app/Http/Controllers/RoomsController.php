<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Role;
use App\Models\Room;
use App\Services\Aios\CommandExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomsController extends Controller
{
    public function index(): View
    {
        return view('rooms.index', [
            'rooms' => Room::withCount(['agents', 'projects'])->orderBy('number')->get(),
        ]);
    }

    public function store(Request $request, CommandExecutor $executor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:8', 'unique:rooms,number'],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $executor->run([
            'type' => 'command',
            'domain' => 'room',
            'action' => 'add',
            'command' => 'room.add',
            'positional' => [$validated['name']],
            'options' => array_filter([
                'number' => $validated['number'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
            ]),
            'raw' => "room add '{$validated['name']}'",
        ]);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['name' => $result['message']]);
        }

        $room = Room::where('number', $result['data']['number'])->firstOrFail();

        return redirect()->route('rooms.show', $room)->with('status', $result['message']);
    }

    public function show(Room $room): View
    {
        $room->load(['agents.role', 'projects']);

        return view('rooms.show', [
            'room' => $room,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function storeAgent(Request $request, Room $room, CommandExecutor $executor): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'combo' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $executor->run([
            'type' => 'command',
            'domain' => 'agent',
            'action' => 'add',
            'command' => 'agent.add',
            'positional' => [],
            'options' => array_filter([
                'room' => $room->number,
                'role' => $validated['role'],
                'name' => $validated['name'] ?? null,
                'model' => $validated['combo'] ?? null,
            ]),
            'raw' => "agent add --room {$room->number} --role '{$validated['role']}'",
        ]);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['role' => $result['message']]);
        }

        return redirect()->route('rooms.show', $room)->with('status', $result['message']);
    }

    public function destroyAgent(Room $room, Agent $agent, CommandExecutor $executor): RedirectResponse
    {
        abort_unless($agent->room_id === $room->id, 404);

        $result = $executor->run([
            'type' => 'command',
            'domain' => 'agent',
            'action' => 'remove',
            'command' => 'agent.remove',
            'positional' => [$agent->slug],
            'options' => ['room' => $room->number],
            'raw' => "agent remove {$agent->slug}",
        ]);

        return redirect()->route('rooms.show', $room)->with('status', $result['message']);
    }

    public function archive(Room $room, CommandExecutor $executor): RedirectResponse
    {
        $result = $executor->run([
            'type' => 'command',
            'domain' => 'room',
            'action' => 'archive',
            'command' => 'room.archive',
            'positional' => [$room->number],
            'options' => [],
            'raw' => "room archive {$room->number}",
        ]);

        return redirect()->route('rooms.index')->with('status', $result['message']);
    }
}
