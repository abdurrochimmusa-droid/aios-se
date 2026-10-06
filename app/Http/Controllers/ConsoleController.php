<?php

namespace App\Http\Controllers;

use App\Models\CommandHistory;
use App\Services\Aios\CommandExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    public function index(): View
    {
        return view('console.index', [
            'histories' => CommandHistory::latest()->limit(15)->get(),
        ]);
    }

    public function preview(Request $request, CommandExecutor $executor): View|RedirectResponse
    {
        $validated = $request->validate(['input' => ['required', 'string', 'max:2000']]);
        $parsed = $executor->parse($validated['input']);

        if ($parsed['type'] === 'natural') {
            $executor->recordPreview($parsed);

            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => 'Bahasa alami diterjemahkan menjadi perintah formal dulu (penerjemah model hadir di Fase 1). Untuk sekarang pakai sintaks formal, mis. room add \'IT Team\'.']);
        }

        if (($parsed['type'] ?? '') !== 'command') {
            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => $parsed['error'] ?? 'Perintah tidak dikenal.']);
        }

        $preview = $executor->preview($parsed);

        if (! $preview['ok']) {
            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => $preview['message']]);
        }

        return view('console.index', [
            'histories' => CommandHistory::latest()->limit(15)->get(),
            'preview' => $preview,
            'rawInput' => $validated['input'],
            'destructive' => $executor->isDestructive($parsed),
        ]);
    }

    public function run(Request $request, CommandExecutor $executor): RedirectResponse
    {
        $validated = $request->validate(['input' => ['required', 'string', 'max:2000']]);
        $parsed = $executor->parse($validated['input']);

        if (($parsed['type'] ?? '') !== 'command') {
            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => 'Perintah tidak valid.']);
        }

        $preview = $executor->preview($parsed);

        if (! $preview['ok']) {
            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => $preview['message']]);
        }

        $result = $executor->run($parsed);

        if (! $result['ok']) {
            return redirect()->route('console.index')
                ->withInput()
                ->withErrors(['input' => $result['message']]);
        }

        return redirect()->route('console.index')->with('status', $result['message']);
    }
}
