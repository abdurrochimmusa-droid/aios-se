@extends('layouts.app')

@section('title', 'Biaya')
@section('subtitle', 'Pemakaian model per proyek, agen, dan combo.')

@section('content')
<div class="flex flex-col gap-4">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total token', 'value' => number_format($totalTokens)],
            ['label' => 'Total panggilan', 'value' => number_format($totalCalls)],
            ['label' => 'Token hari ini', 'value' => number_format($todayTokens)],
            ['label' => 'Panggilan hari ini', 'value' => number_format($todayCalls)],
        ] as $stat)
            <div class="rounded-md border border-zinc-200 bg-white p-4">
                <p class="text-2xl font-semibold">{{ $stat['value'] }}</p>
                <p class="text-sm text-zinc-500">{{ $stat['label'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-md border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold">Per proyek</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-xs text-zinc-500">
                    <th class="px-4 py-3 font-medium">Proyek</th>
                    <th class="px-4 py-3 font-medium">Panggilan</th>
                    <th class="px-4 py-3 font-medium">Token</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byProject as $row)
                    <tr class="border-b border-zinc-100 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ $row->project?->name ?? 'tanpa proyek' }}</td>
                        <td class="px-4 py-3">{{ number_format($row->calls) }}</td>
                        <td class="px-4 py-3">{{ number_format($row->tokens) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3 text-sm text-zinc-500">Belum ada pemakaian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="overflow-hidden rounded-md border border-zinc-200 bg-white">
            <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold">Per agen</h2>
            <table class="w-full text-left text-sm">
                <tbody>
                    @forelse ($byAgent as $row)
                        <tr class="border-b border-zinc-100 last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $row->agent?->slug ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-zinc-500">{{ number_format($row->tokens) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-3 text-sm text-zinc-500">Belum ada pemakaian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="overflow-hidden rounded-md border border-zinc-200 bg-white">
            <h2 class="border-b border-zinc-200 px-4 py-3 font-semibold">Per combo</h2>
            <table class="w-full text-left text-sm">
                <tbody>
                    @forelse ($byModel as $row)
                        <tr class="border-b border-zinc-100 last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $row->model ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-zinc-500">{{ number_format($row->calls) }} panggilan · {{ number_format($row->tokens) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-3 text-sm text-zinc-500">Belum ada pemakaian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>
@endsection
