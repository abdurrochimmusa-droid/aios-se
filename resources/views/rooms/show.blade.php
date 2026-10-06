@extends('layouts.app')

@section('title', "Room-{$room->number} {$room->name}")
@section('subtitle', $room->purpose ?? 'Detail room, agen, dan proyek.')

@section('content')
<div class="flex flex-col gap-4">
 <div class="flex items-center justify-between gap-3">
 <a href="{{ route('rooms.index') }}" class="text-sm text-zinc-500 hover:underline ">← Semua room</a>
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 @if ($room->status === \App\Enums\RoomStatus::Active)
 <form method="POST" action="{{ route('rooms.archive', $room) }}" onsubmit="return confirm('Arsipkan Room-{{ $room->number }}?');">
 @csrf
 <button class="rounded-md px-3 py-1.5 text-sm text-zinc-500 hover:bg-zinc-100 ">Arsipkan</button>
 </form>
 @else
 <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 ">diarsipkan</span>
 @endif
 @endif
 </div>

 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <div class="mb-3 flex items-center justify-between">
 <h2 class="font-semibold">Agen ({{ $room->agents->count() }})</h2>
 </div>
 @if ($room->agents->isEmpty())
 <p class="mb-3 text-sm text-zinc-500 ">Belum ada agen di room ini.</p>
 @else
 <ul class="mb-4 flex flex-col gap-2">
 @foreach ($room->agents as $agent)
 <li class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-sm ">
 <div>
 <p class="font-medium">{{ $agent->slug }}</p>
 <p class="text-xs text-zinc-500 ">{{ $agent->role->name }} · {{ $agent->combo_name ?? 'combo bawaan' }} · {{ $agent->status->value }}</p>
 </div>
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <form method="POST" action="{{ route('rooms.agents.destroy', [$room, $agent]) }}" onsubmit="return confirm('Nonaktifkan {{ $agent->slug }}?');">
 @csrf
 @method('DELETE')
 <button class="text-xs text-rose-600 hover:underline ">Nonaktifkan</button>
 </form>
 @endif
 </li>
 @endforeach
 </ul>
 @endif

 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <h3 class="mb-2 text-sm font-semibold">Tambah agen</h3>
 <form method="POST" action="{{ route('rooms.agents.store', $room) }}" class="grid gap-2 sm:grid-cols-4">
 @csrf
 <select name="role" required class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm sm:col-span-2 ">
 <option value="">— Pilih role —</option>
 @foreach ($roles as $role)
 <option value="{{ $role->slug }}">{{ $role->name }}</option>
 @endforeach
 </select>
 <input name="name" maxlength="255" placeholder="Nama (otomatis)" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm ">
 <input name="combo" maxlength="64" placeholder="Combo (bawaan role)" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm ">
 <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 sm:col-span-4">+ Add Agent</button>
 </form>
 @endif
 </section>

 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <h2 class="mb-3 font-semibold">Proyek ({{ $room->projects->count() }})</h2>
 @if ($room->projects->isEmpty())
 <p class="text-sm text-zinc-500 ">Belum ada proyek. Jalankan lewat Konsol: <code class="rounded bg-zinc-100 px-1 ">project run --room {{ $room->number }} 'Judul'</code></p>
 @else
 <ul class="flex flex-col gap-2">
 @foreach ($room->projects as $project)
 <li class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-sm ">
 <span class="font-medium">{{ $project->name }}</span>
 <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs ">{{ $project->status->value }}</span>
 </li>
 @endforeach
 </ul>
 @endif
 </section>
</div>
@endsection
