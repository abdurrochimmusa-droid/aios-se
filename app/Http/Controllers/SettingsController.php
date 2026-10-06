<?php

namespace App\Http\Controllers;

use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        return view('settings.edit', [
            'values' => [
                'nine_router.base_url' => $settings->get('nine_router.base_url'),
                'nine_router.default_combo' => $settings->get('nine_router.default_combo'),
                'nine_router.timeout' => $settings->get('nine_router.timeout'),
                'nine_router.mock' => $settings->get('nine_router.mock') === '1' ? '1' : '',
                'aios.task_token_budget' => $settings->get('aios.task_token_budget'),
                'aios.delegation_max_depth' => $settings->get('aios.delegation_max_depth'),
                'aios.delegation_max_rounds' => $settings->get('aios.delegation_max_rounds'),
                'aios.projects_root' => $settings->get('aios.projects_root'),
            ],
            'hasApiKey' => $settings->hasApiKey(),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'nine_router_base_url' => ['required', 'url', 'max:255'],
            'nine_router_default_combo' => ['required', 'string', 'max:64'],
            'nine_router_timeout' => ['required', 'integer', 'min:5', 'max:600'],
            'nine_router_mock' => ['sometimes', 'boolean'],
            'api_key' => ['nullable', 'string', 'min:8', 'max:500'],
            'task_token_budget' => ['required', 'integer', 'min:1000'],
            'delegation_max_depth' => ['required', 'integer', 'min:1', 'max:10'],
            'delegation_max_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'projects_root' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->setMany([
            'nine_router.base_url' => $validated['nine_router_base_url'],
            'nine_router.default_combo' => $validated['nine_router_default_combo'],
            'nine_router.timeout' => (string) $validated['nine_router_timeout'],
            'nine_router.mock' => ! empty($validated['nine_router_mock']) ? '1' : '',
            'aios.task_token_budget' => (string) $validated['task_token_budget'],
            'aios.delegation_max_depth' => (string) $validated['delegation_max_depth'],
            'aios.delegation_max_rounds' => (string) $validated['delegation_max_rounds'],
            'aios.projects_root' => $validated['projects_root'] ?? '',
        ]);

        if (! empty($validated['api_key'])) {
            $settings->setApiKey($validated['api_key']);
        }

        return redirect()->route('settings.edit')->with('status', 'Pengaturan disimpan.');
    }

    public function test(NineRouterClient $client): RedirectResponse
    {
        try {
            $result = $client->chat(
                [['role' => 'user', 'content' => 'Balas hanya dengan kata: ok']],
                null,
                ['max_tokens' => 16]
            );
        } catch (NineRouterException $e) {
            return redirect()->route('settings.edit')->withErrors(['connection' => $e->getMessage()]);
        }

        return redirect()->route('settings.edit')->with(
            'status',
            "Koneksi OK: combo '{$result['model']}', {$result['tokens_in']}+{$result['tokens_out']} token, {$result['latency_ms']} ms."
        );
    }
}
