@extends('layouts.app')

@section('title', $project->name)
@section('subtitle', $project->idea ?? '')

@section('content')
<div class="flex flex-col gap-4">
 <div class="flex flex-wrap items-center gap-2">
 <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium ">{{ $project->status->value }}</span>
 <div class="h-2 min-w-32 flex-1 overflow-hidden rounded-full bg-zinc-200 sm:max-w-xs ">
 <div class="h-full rounded-full bg-brand-600" style="width: {{ $progress }}%"></div>
 </div>
 <span class="text-xs text-zinc-500 ">{{ $progress }}%</span>
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <span class="ml-auto flex gap-2">
 @if ($project->status === \App\Enums\ProjectStatus::Running)
 <form method="POST" action="{{ route('projects.pause', $project) }}">@csrf<button class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 ">Jeda</button></form>
 @endif
 @if ($project->status === \App\Enums\ProjectStatus::Paused)
 <form method="POST" action="{{ route('projects.resume', $project) }}">@csrf<button class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 ">Lanjut</button></form>
 @endif
 @if (! in_array($project->status, [\App\Enums\ProjectStatus::Done], true))
 <form method="POST" action="{{ route('projects.cancel', $project) }}" onsubmit="return confirm('Hentikan proyek ini?');">@csrf<button class="rounded-md border border-rose-300 px-3 py-1.5 text-sm text-rose-600 hover:bg-rose-50 ">Hentikan</button></form>
 @endif
 </span>
 @endif
 </div>

 @if ($pendingApprovals->isNotEmpty())
 <section class="rounded-md border border-amber-300 bg-amber-50 p-4 ">
 <h2 class="mb-3 font-semibold">Menunggu persetujuan ({{ $pendingApprovals->count() }})</h2>
 <ul class="flex flex-col gap-3">
 @foreach ($pendingApprovals as $approval)
 <li class="rounded-md bg-white p-3 text-sm ">
 <p class="font-medium">Gerbang {{ $approval->stage }}</p>
 @if ($approval->artifact)
 <details class="mt-1">
 <summary class="cursor-pointer text-xs text-zinc-500 ">Lihat {{ $approval->artifact->type }} v{{ $approval->artifact->version }}</summary>
 <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-md bg-zinc-50 p-3 font-mono text-xs ">{{ $approval->artifact->body }}</pre>
 </details>
 @endif
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager, \App\Enums\UserRole::Approver], true))
 <div class="mt-2 flex gap-2">
 <form method="POST" action="{{ route('projects.approvals.approve', [$project, $approval]) }}">@csrf<button class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Setujui</button></form>
 <form method="POST" action="{{ route('projects.approvals.reject', [$project, $approval]) }}" class="flex flex-1 gap-2">
 @csrf
 <input name="note" maxlength="1000" placeholder="Alasan penolakan (wajib jelas)" class="flex-1 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm ">
 <button class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm hover:bg-zinc-50 ">Tolak</button>
 </form>
 </div>
 @else
 <p class="mt-2 text-xs text-zinc-500 ">Menunggu owner, manager, atau approver.</p>
 @endif
 </li>
 @endforeach
 </ul>
 </section>
 @endif

 <section class="overflow-hidden rounded-md border border-zinc-200 bg-white ">
 <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold ">Linimasa tahap</h2>
 <table class="w-full text-left text-sm">
 <thead>
 <tr class="border-b border-zinc-200 text-xs text-zinc-500 ">
 <th class="px-4 py-3 font-medium">Tahap</th>
 <th class="px-4 py-3 font-medium">Agen</th>
 <th class="px-4 py-3 font-medium">Status</th>
 <th class="px-4 py-3 font-medium">Token</th>
 <th class="px-4 py-3"></th>
 </tr>
 </thead>
 <tbody>
 @foreach ($project->tasks->sortBy('step') as $task)
 <tr class="border-b border-zinc-100 last:border-0 ">
 <td class="px-4 py-3">
 <p class="font-medium">{{ $task->title }}</p>
 @if ($task->error)
 <p class="text-xs text-rose-600 ">{{ $task->error }}</p>
 @endif
 </td>
 <td class="px-4 py-3 text-zinc-500 ">{{ $task->agent?->slug ?? '—' }}</td>
 <td class="px-4 py-3">
 <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs ">{{ $task->status->value }}</span>
 </td>
 <td class="px-4 py-3 text-zinc-500 ">{{ number_format($task->tokens_in + $task->tokens_out) }}</td>
 <td class="px-4 py-3 text-right">
 @if (in_array($task->status, [\App\Enums\TaskStatus::Failed, \App\Enums\TaskStatus::Paused], true) && in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <form method="POST" action="{{ route('projects.tasks.retry', [$project, $task]) }}">@csrf<button class="text-xs text-brand-600 hover:underline ">Ulangi</button></form>
 @endif
 </td>
 </tr>
 @endforeach
 </tbody>
 </table>
 </section>
</div>
@endsection
