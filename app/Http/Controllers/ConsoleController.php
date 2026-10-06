<?php

namespace App\Http\Controllers;

use App\Models\CommandHistory;
use App\Services\Aios\CommandExecutor;
use App\Services\Aios\NaturalCommandTranslator;
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

    public function preview(Request $request, CommandExecutor $executor, NaturalCommandTranslator $translator): View|RedirectResponse
    {
        $validated = $request->validate(['input' => ['required', 'string', 'max:2000']]);
        $parsed = $executor->parse($validated['input']);
        $translatedFrom = null;

        if ($parsed['type'] === 'natural') {
            $translation = $translator->translate($validated['input']);

            if (! $translation['ok']) {
                $executor->recordPreview($parsed);

                return redirect()->route('console.index')
                    ->withInput()
                    ->withErrors(['input' => $translation['message']]);
            }

            $parsed = $translation['parsed'];
            $translatedFrom = $validated['input'];
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
            'rawInput' => $parsed['raw'],
            'translatedFrom' => $translatedFrom,
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
