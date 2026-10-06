@extends('layouts.app')

@section('title', 'Persetujuan')
@section('subtitle', 'Gerbang manusia: PRD, desain, skema, dan rilis.')

@section('content')
<div class="flex flex-col gap-4">
    <section class="rounded-md border border-zinc-200 bg-white p-4">
        <h2 class="mb-3 font-semibold">Menunggu ({{ $pending->count() }})</h2>
        @if ($pending->isEmpty())
            <p class="text-sm text-zinc-500">Tidak ada yang menunggu keputusan.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($pending as $approval)
                    <li class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium">
                                <a href="{{ route('projects.show', $approval->project) }}" class="hover:underline">{{ $approval->project->name }}</a>
                                <span class="font-normal text-zinc-500">· gerbang {{ $approval->stage }} · {{ $approval->created_at->diffForHumans() }}</span>
                            </p>
                        </div>
                        @if ($approval->artifact)
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-zinc-500">Lihat {{ $approval->artifact->type }} v{{ $approval->artifact->version }}</summary>
                                <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-md bg-white p-3 font-mono text-xs">{{ $approval->artifact->body }}</pre>
                            </details>
                        @endif
                        @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Approver], true))
                            <div class="mt-2 flex gap-2">
                                <form method="POST" action="{{ route('approvals.approve', $approval) }}">@csrf<button class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Setujui</button></form>
                                <form method="POST" action="{{ route('approvals.reject', $approval) }}" class="flex flex-1 gap-2">
                                    @csrf
                                    <input name="note" maxlength="1000" placeholder="Alasan penolakan" class="flex-1 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm">
                                    <button class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50">Tolak</button>
                                </form>
                            </div>
                        @else
                            <p class="mt-2 text-xs text-zinc-500">Menunggu owner, manager, atau approver.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="rounded-md border border-zinc-200 bg-white p-4">
        <h2 class="mb-3 font-semibold">Riwayat keputusan</h2>
        @if ($recent->isEmpty())
            <p class="text-sm text-zinc-500">Belum ada keputusan.</p>
        @else
            <ul class="flex flex-col gap-2">
                @foreach ($recent as $approval)
                    <li class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-sm">
                        <span>{{ $approval->project->name }} · gerbang {{ $approval->stage }}</span>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $approval->status->value }} · {{ $approval->decider?->name ?? '-' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
