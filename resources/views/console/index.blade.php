@extends('layouts.app')

@section('title', 'Konsol Perintah')
@section('subtitle', 'Satu baris perintah untuk room, agen, role, dan proyek. Selalu pratinjau dulu.')

@section('content')
<div class="flex max-w-2xl flex-col gap-4">
 <form method="POST" action="{{ route('console.preview') }}" class="rounded-md border border-zinc-200 bg-white p-4 ">
 @csrf
 <label class="flex flex-col gap-2 text-sm">
 Perintah
 <div class="flex gap-2">
 <input name="input" value="{{ old('input', $rawInput ?? '') }}" required maxlength="2000" placeholder="room add 'IT Team'" autocomplete="off" class="flex-1 rounded-md border border-zinc-300 bg-white px-3 py-2 font-mono text-sm ">
 <button class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Pratinjau</button>
 </div>
 </label>
 <p class="mt-2 text-xs text-zinc-400">Contoh: <code>agent add --room 01 --role 'Backend Dev' --model combo-coding</code> · <code>room show 01</code> · <code>project run --room 01 'Judul'</code></p>
 </form>

 @isset($preview)
 <div class="rounded-md border border-brand-600/40 bg-white p-4 ">
 <h2 class="mb-1 font-semibold">Pratinjau</h2>
 <p class="mb-3 text-sm">{{ $preview['message'] }}</p>
 @if ($destructive)
 <p class="mb-3 text-sm font-medium text-amber-700 ">Perintah ini mengubah arsip/hubungan/data — pastikan sudah benar.</p>
 @endif
 @if (in_array(auth()->user()->role, [\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager], true))
 <form method="POST" action="{{ route('console.run') }}" class="flex gap-2">
 @csrf
 <input type="hidden" name="input" value="{{ $rawInput }}">
 <button class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Jalankan</button>
 <a href="{{ route('console.index') }}" class="rounded-md px-4 py-2 text-sm text-zinc-500 hover:bg-zinc-100 ">Batal</a>
 </form>
 @else
 <p class="text-sm text-zinc-500 ">Peran Anda hanya bisa melihat pratinjau. Eksekusi oleh owner/manager.</p>
 @endif
 </div>
 @endisset

 <section class="rounded-md border border-zinc-200 bg-white p-4 ">
 <h2 class="mb-3 font-semibold">Riwayat</h2>
 @if ($histories->isEmpty())
 <p class="text-sm text-zinc-500 ">Belum ada perintah.</p>
 @else
 <ul class="flex flex-col gap-2">
 @foreach ($histories as $history)
 <li class="flex items-center justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-sm ">
 <code class="truncate font-mono text-xs">{{ $history->raw_input }}</code>
 <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs ">{{ $history->status->value }}</span>
 </li>
 @endforeach
 </ul>
 @endif
 </section>
</div>
@endsection
