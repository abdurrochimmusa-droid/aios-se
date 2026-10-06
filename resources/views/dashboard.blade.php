@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
<div class="flex flex-col gap-6">
 <div>
 <h1 class="text-xl font-semibold">Beranda</h1>
 <p class="text-sm text-zinc-500 ">Ringkasan proyek, room aktif, antrean persetujuan, dan biaya hari ini.</p>
 </div>

 <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
 @foreach ([
 ['label' => 'Room aktif', 'value' => $stats['rooms']],
 ['label' => 'Agen aktif', 'value' => $stats['agents']],
 ['label' => 'Proyek berjalan', 'value' => $stats['projects']],
 ['label' => 'Menunggu persetujuan', 'value' => $stats['approvals']],
 ] as $stat)
 <div class="rounded-md border border-zinc-200 bg-white p-4 ">
 <p class="text-2xl font-semibold">{{ $stat['value'] }}</p>
 <p class="text-sm text-zinc-500 ">{{ $stat['label'] }}</p>
 </div>
 @endforeach
 </div>

 <div class="grid gap-3 lg:grid-cols-2">
 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <div class="mb-3 flex items-center justify-between">
 <h2 class="font-semibold">Antrean persetujuan</h2>
 <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 ">{{ $pendingApprovals->count() }}</span>
 </div>
 @if ($pendingApprovals->isEmpty())
 <p class="text-sm text-zinc-500 ">Tidak ada yang menunggu. Gerbang PRD, desain, skema, dan rilis akan muncul di sini.</p>
 @else
 <ul class="flex flex-col gap-2">
 @foreach ($pendingApprovals as $approval)
 <li class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-sm ">
 <div>
 <p class="font-medium">{{ $approval->project->name }}</p>
 <p class="text-xs text-zinc-500 ">Tahap {{ $approval->stage }} · {{ $approval->created_at->diffForHumans() }}</p>
 </div>
 <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 ">pending</span>
 </li>
 @endforeach
 </ul>
 @endif
 </section>

 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <h2 class="mb-3 font-semibold">Biaya hari ini</h2>
 <div class="flex items-baseline gap-2">
 <p class="text-2xl font-semibold">{{ number_format($todayTokens) }}</p>
 <p class="text-sm text-zinc-500 ">token · {{ $todayCalls }} panggilan model</p>
 </div>
 @if ($topAgents->isEmpty())
 <p class="mt-2 text-sm text-zinc-500 ">Belum ada pemakaian tercatat.</p>
 @else
 <ul class="mt-3 flex flex-col gap-2">
 @foreach ($topAgents as $row)
 <li class="flex items-center justify-between gap-3 text-sm">
 <span>{{ $row->agent?->slug ?? 'tanpa agen' }}</span>
 <span class="text-zinc-500 ">{{ number_format($row->tokens) }} token</span>
 </li>
 @endforeach
 </ul>
 @endif
 </section>
 </div>

 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <h2 class="mb-3 font-semibold">Room</h2>
 @if ($rooms->isEmpty())
 <p class="text-sm text-zinc-500 ">Belum ada room. Buat lewat menu Room atau Konsol.</p>
 @else
 <ul class="grid gap-2 sm:grid-cols-2">
 @foreach ($rooms as $room)
 <li class="rounded-md bg-zinc-50 px-3 py-2 text-sm ">
 <p class="font-medium">Room-{{ $room->number }} {{ $room->name }}</p>
 <p class="text-xs text-zinc-500 ">{{ $room->agents_count }} agen · {{ $room->projects_count }} proyek</p>
 </li>
 @endforeach
 </ul>
 @endif
 </section>
</div>
@endsection
