<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <title>AIOS-SE — @yield('title', 'Beranda')</title>
 @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-100 text-zinc-900 antialiased ">
 <div class="min-h-full lg:flex">
 <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

 <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col gap-1 overflow-y-auto border-r border-zinc-200 bg-white px-3 py-4 lg:sticky lg:top-0 lg:flex lg:h-screen ">
 <div class="mb-3 flex items-center gap-3 px-2">
 <span class="grid size-9 shrink-0 place-items-center rounded-md bg-brand-600 text-lg font-bold text-white">A</span>
 <div>
 <p class="text-sm font-semibold">AIOS-SE</p>
 <p class="text-xs text-zinc-500 ">Studio agen virtual</p>
 </div>
 </div>

 <p class="px-2 pt-2 text-[11px] font-semibold tracking-wider text-zinc-400 ">MAIN MENU</p>
 <nav class="flex flex-col gap-0.5">
 <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Beranda</a>
 <a href="{{ route('rooms.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('rooms.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Room</a>
 <a href="{{ route('console.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('console.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Konsol Perintah</a>
 <a href="{{ route('projects.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('projects.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Proyek</a>
 <a href="{{ route('approvals.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('approvals.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Persetujuan</a>
 <a href="{{ route('roles.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('roles.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Agen & Role</a>
 <a href="{{ route('costs.index') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('costs.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Biaya</a>
 </nav>

 <p class="px-2 pt-4 text-[11px] font-semibold tracking-wider text-zinc-400 ">SETUP</p>
 <nav class="flex flex-col gap-0.5">
 @if (auth()->user()->role === \App\Enums\UserRole::Owner)
 <a href="{{ route('settings.edit') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Settings</a>
 @endif
 <a href="{{ route('profile.edit') }}" class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('profile.*') ? 'bg-zinc-100 ' : 'text-zinc-600 hover:bg-zinc-50 ' }}">Profil</a>
 </nav>

 <p class="mt-auto px-2 pt-4 text-xs text-zinc-400 ">v1.0 · self-hosted · 9Router</p>
 </aside>

 <div class="min-w-0 flex-1">
 <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur ">
 <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
 <button id="sidebar-toggle" class="rounded-md p-1.5 hover:bg-zinc-100 lg:hidden " aria-label="Menu">
 <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
 </button>
 <div>
 <h1 class="text-base font-semibold">@yield('title', 'Beranda')</h1>
 <p class="hidden text-xs text-zinc-500 sm:block ">@yield('subtitle', '')</p>
 </div>
 <div class="ml-auto flex items-center gap-2 text-sm">
 <a href="{{ route('profile.edit') }}" class="hidden rounded-full bg-zinc-100 px-2 py-0.5 text-xs sm:block ">{{ auth()->user()->name }} · {{ auth()->user()->role->value }}</a>
 <form method="POST" action="{{ route('logout') }}">
 @csrf
 <button class="rounded-md px-3 py-1.5 text-sm text-zinc-500 hover:bg-zinc-100 ">Keluar</button>
 </form>
 </div>
 </div>
 </header>

 <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
 @if (session('status'))
 <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ">{{ session('status') }}</div>
 @endif
 @if ($errors->any())
 <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 ">
 <ul class="list-inside list-disc">
 @foreach ($errors->all() as $error)
 <li>{{ $error }}</li>
 @endforeach
 </ul>
 </div>
 @endif

 @yield('content')
 </main>
 </div>
 </div>

 <script>
 const sidebar = document.getElementById('sidebar');
 const backdrop = document.getElementById('sidebar-backdrop');
 const toggle = document.getElementById('sidebar-toggle');
 function setSidebar(open) {
 sidebar.classList.toggle('hidden', !open);
 sidebar.classList.toggle('flex', open);
 backdrop.classList.toggle('hidden', !open);
 }
 toggle?.addEventListener('click', () => setSidebar(sidebar.classList.contains('hidden')));
 backdrop?.addEventListener('click', () => setSidebar(false));
 </script>
</body>
</html>
