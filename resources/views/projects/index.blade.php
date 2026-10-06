@extends('layouts.app')

@section('title', 'Proyek')
@section('subtitle', 'Tujuan yang dikerjakan satu room dari ide sampai teruji.')

@section('content')
<div class="flex flex-col gap-4">
 @if ($projects->isEmpty())
 <div class="rounded-md border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 ">
 Belum ada proyek. Jalankan lewat Konsol: <code class="rounded bg-zinc-100 px-1 ">project run --room 01 'Judul'</code>
 </div>
 @else
 <div class="overflow-hidden rounded-md border border-zinc-200 bg-white ">
 <table class="w-full text-left text-sm">
 <thead>
 <tr class="border-b border-zinc-200 text-xs text-zinc-500 ">
 <th class="px-4 py-3 font-medium">Proyek</th>
 <th class="px-4 py-3 font-medium">Room</th>
 <th class="px-4 py-3 font-medium">Status</th>
 <th class="px-4 py-3 font-medium">Tahap</th>
 <th class="px-4 py-3"></th>
 </tr>
 </thead>
 <tbody>
 @foreach ($projects as $project)
 <tr class="border-b border-zinc-100 last:border-0 ">
 <td class="px-4 py-3 font-medium">{{ $project->name }}</td>
 <td class="px-4 py-3 text-zinc-500 ">{{ $project->room->name ?? '-' }}</td>
 <td class="px-4 py-3">
 <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs ">{{ $project->status->value }}</span>
 </td>
 <td class="px-4 py-3 text-zinc-500 ">{{ $project->tasks_count }} tahap</td>
 <td class="px-4 py-3 text-right">
 <a href="{{ route('projects.show', $project) }}" class="text-sm text-brand-600 hover:underline ">Buka</a>
 </td>
 </tr>
 @endforeach
 </tbody>
 </table>
 </div>
 @endif
</div>
@endsection
