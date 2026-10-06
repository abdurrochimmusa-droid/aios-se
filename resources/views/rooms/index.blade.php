@extends('layouts.app')

@section('title', 'Room')
@section('subtitle', 'Ruang kerja berisi satu tim agen untuk satu tujuan.')

@section('content')
<div class="flex flex-col gap-4">
 <div class="flex items-center justify-between gap-3">
 <p class="text-sm text-zinc-500 ">{{ $rooms->count() }} room</p>
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <button id="add-room-btn" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">+ Add Room</button>
 @endif
 </div>

 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <div id="add-room-form" class="hidden rounded-md border border-zinc-200 bg-white p-4 ">
 <h2 class="mb-3 font-semibold">Room baru</h2>
 <form method="POST" action="{{ route('rooms.store') }}" class="flex flex-col gap-3">
 @csrf
 <div class="grid gap-3 sm:grid-cols-2">
 <label class="flex flex-col gap-1 text-sm">
 Nama room
 <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="IT Team" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 <label class="flex flex-col gap-1 text-sm">
 Nomor <span class="font-normal text-zinc-400">— otomatis bila kosong</span>
 <input name="number" value="{{ old('number') }}" maxlength="8" placeholder="01" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 </div>
 <label class="flex flex-col gap-1 text-sm">
 Tujuan
 <input name="purpose" value="{{ old('purpose') }}" maxlength="1000" placeholder="Membangun aplikasi dari ide sampai teruji" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 <div class="flex gap-2">
 <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Simpan</button>
 <button type="button" id="add-room-cancel" class="rounded-md px-4 py-2 text-sm text-zinc-500 hover:bg-zinc-100 ">Batal</button>
 </div>
 </form>
 </div>
 @endif

 @if ($rooms->isEmpty())
 <div class="rounded-md border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 ">
 Belum ada room. Klik <strong>+ Add Room</strong> atau lewat Konsol.
 </div>
 @else
 <div class="overflow-hidden rounded-md border border-zinc-200 bg-white ">
 <table class="w-full text-left text-sm">
 <thead>
 <tr class="border-b border-zinc-200 text-xs text-zinc-500 ">
 <th class="px-4 py-3 font-medium">Room</th>
 <th class="px-4 py-3 font-medium">Status</th>
 <th class="px-4 py-3 font-medium">Agen</th>
 <th class="px-4 py-3 font-medium">Proyek</th>
 <th class="px-4 py-3"></th>
 </tr>
 </thead>
 <tbody>
 @foreach ($rooms as $room)
 <tr class="border-b border-zinc-100 last:border-0 ">
 <td class="px-4 py-3">
 <a href="{{ route('rooms.show', $room) }}" class="font-medium hover:underline">Room-{{ $room->number }} {{ $room->name }}</a>
 @if ($room->purpose)
 <p class="text-xs text-zinc-500 ">{{ $room->purpose }}</p>
 @endif
 </td>
 <td class="px-4 py-3">
 <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $room->status === \App\Enums\RoomStatus::Active ? 'bg-emerald-100 text-emerald-800 ' : 'bg-zinc-100 text-zinc-500 ' }}">{{ $room->status->value }}</span>
 </td>
 <td class="px-4 py-3">{{ $room->agents_count }}</td>
 <td class="px-4 py-3">{{ $room->projects_count }}</td>
 <td class="px-4 py-3 text-right">
 <a href="{{ route('rooms.show', $room) }}" class="text-sm text-brand-600 hover:underline ">Buka</a>
 </td>
 </tr>
 @endforeach
 </tbody>
 </table>
 </div>
 @endif
</div>

<script>
 const form = document.getElementById('add-room-form');
 document.getElementById('add-room-btn')?.addEventListener('click', () => form?.classList.toggle('hidden'));
 document.getElementById('add-room-cancel')?.addEventListener('click', () => form?.classList.add('hidden'));
 @if ($errors->any() && old('name') !== null)
 form?.classList.remove('hidden');
 @endif
</script>
@endsection
