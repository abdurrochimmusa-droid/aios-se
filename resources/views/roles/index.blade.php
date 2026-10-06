@extends('layouts.app')

@section('title', 'Agen & Role')
@section('subtitle', 'Pustaka role dan seluruh agen. Role baru otomatis ikut serah terima.')

@section('content')
<div class="flex flex-col gap-4">
    <section class="overflow-hidden rounded-md border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold">Role ({{ $roles->count() }})</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-xs text-zinc-500">
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Tool</th>
                    <th class="px-4 py-3 font-medium">Output</th>
                    <th class="px-4 py-3 font-medium">Agen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr class="border-b border-zinc-100 last:border-0">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $role->name }}</p>
                            @if ($role->is_builtin)
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] text-zinc-500">bawaan</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ implode(', ', $role->allowed_tools ?? []) }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ implode(', ', $role->outputs ?? []) }}</td>
                        <td class="px-4 py-3">{{ $role->agents_count }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
        <section class="rounded-md border border-zinc-200 bg-white p-4">
            <h2 class="mb-3 font-semibold">Tambah role</h2>
            <form method="POST" action="{{ route('roles.store') }}" class="grid gap-2 sm:grid-cols-2">
                @csrf
                <input name="name" required maxlength="255" placeholder="Nama role, mis. Security Specialist" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm sm:col-span-2">
                <input name="desc" maxlength="1000" placeholder="Deskripsi singkat" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm sm:col-span-2">
                <input name="tools" maxlength="255" placeholder="Tool: file,git,sandbox,web,db" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm">
                <input name="combo" maxlength="64" placeholder="Combo bawaan (opsional)" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm">
                <input name="inputs" maxlength="255" placeholder="Input: prd,wireframe" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm">
                <input name="outputs" maxlength="255" placeholder="Output: test-report" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm">
                <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 sm:col-span-2">+ Add Role</button>
            </form>
        </section>
    @endif

    <section class="overflow-hidden rounded-md border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold">Agen ({{ $agents->count() }})</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-xs text-zinc-500">
                    <th class="px-4 py-3 font-medium">Agen</th>
                    <th class="px-4 py-3 font-medium">Room</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Combo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $agent)
                    <tr class="border-b border-zinc-100 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ $agent->slug }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $agent->room?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $agent->role->name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ $agent->combo_name ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
@endsection
