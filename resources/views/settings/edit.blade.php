@extends('layouts.app')

@section('title', 'Settings')
@section('subtitle', 'Koneksi 9Router, combo, anggaran, dan batas orkestrasi.')

@section('content')
<div class="flex max-w-2xl flex-col gap-4">
 <form method="POST" action="{{ route('settings.update') }}" class="flex flex-col gap-4 rounded-md border border-zinc-200 bg-white p-4 ">
 @csrf
 @method('PUT')

 <div>
 <h2 class="font-semibold">9Router — gerbang model</h2>
 <p class="text-sm text-zinc-500 ">Satu-satunya jalan keluar ke model AI. Combo dan fallback dikelola di 9Router.</p>
 </div>

 <label class="flex flex-col gap-1 text-sm">
 Base URL
 <input name="nine_router_base_url" value="{{ old('nine_router_base_url', $values['nine_router.base_url']) }}" required maxlength="255" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>

 <div class="grid gap-4 sm:grid-cols-2">
 <label class="flex flex-col gap-1 text-sm">
 API key
 <input type="password" name="api_key" autocomplete="new-password" maxlength="500" placeholder="{{ $hasApiKey ? '•••••• tersimpan (isi untuk mengganti)' : 'Belum diisi' }}" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 <span class="text-xs text-zinc-400">Terenkripsi di database, tak pernah masuk log. Kosongkan bila tidak diganti.</span>
 </label>
 <label class="flex flex-col gap-1 text-sm">
 Combo bawaan
 <input name="nine_router_default_combo" value="{{ old('nine_router_default_combo', $values['nine_router.default_combo']) }}" required maxlength="64" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 </div>

 <label class="flex flex-col gap-1 text-sm">
 Timeout respons (detik)
 <input type="number" name="nine_router_timeout" value="{{ old('nine_router_timeout', $values['nine_router.timeout']) }}" required min="5" max="600" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 <label class="flex flex-row items-center gap-2 text-sm">
 <input type="checkbox" name="nine_router_mock" value="1" @checked(old('nine_router_mock', $values['nine_router.mock']) === '1') class="rounded-md border-zinc-300">
 <span>Mode mock — jawab tanpa API key (untuk demo alur & pengembangan UI)</span>
 </label>

 <div class="border-t border-zinc-200 pt-4 ">
 <h2 class="font-semibold">Anggaran & batas orkestrasi</h2>
 </div>

 <div class="grid gap-4 sm:grid-cols-3">
 <label class="flex flex-col gap-1 text-sm">
 Token per tugas
 <input type="number" name="task_token_budget" value="{{ old('task_token_budget', $values['aios.task_token_budget']) }}" required min="1000" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 <label class="flex flex-col gap-1 text-sm">
 Kedalaman delegasi
 <input type="number" name="delegation_max_depth" value="{{ old('delegation_max_depth', $values['aios.delegation_max_depth']) }}" required min="1" max="10" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 <label class="flex flex-col gap-1 text-sm">
 Putaran delegasi
 <input type="number" name="delegation_max_rounds" value="{{ old('delegation_max_rounds', $values['aios.delegation_max_rounds']) }}" required min="1" max="20" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>
 </div>

 <label class="flex flex-col gap-1 text-sm">
 Folder proyek <span class="text-zinc-400">(produksi: /var/www/folder-proyek)</span>
 <input name="projects_root" value="{{ old('projects_root', $values['aios.projects_root']) }}" maxlength="255" placeholder="storage/app/projects" class="rounded-md border border-zinc-300 bg-white px-3 py-2 ">
 </label>

 <div class="flex flex-wrap gap-2">
 <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Simpan</button>
 </div>
 </form>

 <form method="POST" action="{{ route('settings.test') }}" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 bg-white p-4 ">
 @csrf
 <p class="text-sm text-zinc-500 ">Kirim satu pesan ringan ke combo bawaan untuk memastikan koneksi.</p>
 <button class="shrink-0 rounded-md border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 ">Test koneksi</button>
 </form>
</div>
@endsection
