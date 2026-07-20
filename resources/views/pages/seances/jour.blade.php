<?php

use App\Models\Place;
use App\Models\Seance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.jour');

$user = auth()->user();
$isCoach = $user?->hasRole('coach') ?? false;
$isCollab = $user?->hasRole('collaborator') ?? false;
$canCreate = $user?->can('create', Seance::class) ?? false;

$day = Carbon::parse(request('date', now()->toDateString()));
$dayString = $day->toDateString();

$placesQuery = Place::orderBy('type')->orderBy('name');
if ($isCollab && $user?->agency_id !== null) {
    $placesQuery->where(fn ($q) => $q->where('type', 'external')->orWhere('id', $user->agency_id));
}
$places = $placesQuery->get();

$seancesQuery = Seance::with(['coach', 'place'])
    ->withCount(['participants as registered_count' => fn ($q) => $q->where('seance_user.status', 'registered')])
    ->whereDate('started_at', $dayString)
    ->orderBy('started_at');

if ($isCoach) {
    $seancesQuery->where('coach_id', $user?->id);
}

$byPlace = $seancesQuery->get()->groupBy('place_id');

$myStatuses = collect();
if ($user !== null) {
    $myStatuses = DB::table('seance_user')->where('user_id', $user->id)->pluck('status', 'seance_id');
}

$dayStartHour = 6;
$dayEndHour = 21;
$hours = range($dayStartHour, $dayEndHour - 1);
$pxPerHour = 56;
$columnHeight = ($dayEndHour - $dayStartHour) * $pxPerHour;

$statusOf = function (Seance $seance) use ($myStatuses): string {
    if ($seance->cancelled_at !== null) {
        return 'cancelled';
    }
    $mine = $myStatuses[$seance->id] ?? null;
    if ($mine === 'registered') {
        return 'registered';
    }
    if ($mine === 'waitlist') {
        return 'waitlist';
    }
    $full = $seance->max_participants !== null && $seance->registered_count >= $seance->max_participants;

    return $full ? 'full' : 'available';
};

$colors = [
    'available' => '#0ea5e9',
    'registered' => '#22c55e',
    'full' => '#c8102e',
    'waitlist' => '#eab308',
    'cancelled' => '#6b7280',
];

?>

<x-app-layout title="Planning par lieu">
    <section class="panel mb-6 p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold">Planning par lieu</h1>
                <p class="text-sm text-base-content/70">{{ $day->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <nav class="flex gap-1">
                    <span class="btn btn-primary btn-sm">Jour par lieu</span>
                    <a href="{{ route('seances.index') }}" class="btn btn-ghost btn-sm">Calendrier</a>
                </nav>
                <a href="{{ route('seances.jour', ['date' => $day->copy()->subDay()->toDateString()]) }}" class="btn btn-outline btn-sm">←</a>
                <a href="{{ route('seances.jour') }}" class="btn btn-ghost btn-sm">Aujourd'hui</a>
                <a href="{{ route('seances.jour', ['date' => $day->copy()->addDay()->toDateString()]) }}" class="btn btn-outline btn-sm">→</a>
                @can('create', App\Models\Seance::class)
                    <a href="{{ route('seances.create') }}" class="btn btn-primary btn-sm">+ Nouvelle séance</a>
                @endcan
            </div>
        </div>
    </section>

    <section class="panel hidden overflow-x-auto p-4 lg:block">
        <div class="flex min-w-max">
            <div class="w-14 shrink-0 pt-8">
                @foreach ($hours as $h)
                    <div class="relative text-right text-xs text-base-content/50" style="height: {{ $pxPerHour }}px">
                        <span class="absolute -top-2 right-2">{{ $h }}h</span>
                    </div>
                @endforeach
            </div>

            @foreach ($places as $place)
                <div class="w-48 shrink-0 border-l border-white/10">
                    <div class="flex h-8 items-center justify-center border-b border-white/10 text-xs font-bold uppercase tracking-wide">
                        {{ $place->name }}
                        <span class="ml-1 badge badge-xs {{ $place->type === 'external' ? 'badge-info' : 'badge-primary' }}">{{ $place->type === 'external' ? 'ext' : 'ag' }}</span>
                    </div>
                    <div class="relative" style="height: {{ $columnHeight }}px">
                        @foreach ($hours as $h)
                            @php $cellTop = ($h - $dayStartHour) * $pxPerHour; @endphp
                            @if ($canCreate)
                                <a href="{{ route('seances.create', ['place_id' => $place->id, 'date' => $dayString, 'start' => sprintf('%02d:00', $h)]) }}"
                                   class="absolute inset-x-0 block border-b border-white/5 transition hover:bg-white/5"
                                   style="top: {{ $cellTop }}px; height: {{ $pxPerHour }}px"
                                   title="Créer une séance à {{ $h }}h"></a>
                            @else
                                <div class="absolute inset-x-0 border-b border-white/5" style="top: {{ $cellTop }}px; height: {{ $pxPerHour }}px"></div>
                            @endif
                        @endforeach

                        @foreach (($byPlace[$place->id] ?? collect()) as $seance)
                            @php
                                $status = $statusOf($seance);
                                $start = $seance->started_at;
                                $end = $seance->ended_at ?? $start->copy()->addMinutes(45);
                                $top = (($start->hour - $dayStartHour) * 60 + $start->minute) * ($pxPerHour / 60);
                                $height = max($start->diffInMinutes($end) * ($pxPerHour / 60), 22);
                            @endphp
                            <a href="{{ route('seances.show', ['seance' => $seance->id]) }}"
                               class="absolute inset-x-1 z-10 overflow-hidden rounded p-1 text-xs font-semibold text-white shadow"
                               style="top: {{ $top }}px; height: {{ $height }}px; background: {{ $colors[$status] }}">
                                {{ $start->format('H:i') }} {{ $seance->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex flex-col gap-4 lg:hidden">
        @forelse ($places as $place)
            @php $placeSeances = ($byPlace[$place->id] ?? collect()); @endphp
            @if ($placeSeances->isNotEmpty())
                <section class="panel p-4">
                    <h2 class="mb-2 font-bold">{{ $place->name }}</h2>
                    <ul class="flex flex-col gap-2">
                        @foreach ($placeSeances as $seance)
                            @php $status = $statusOf($seance); @endphp
                            <li>
                                <a href="{{ route('seances.show', ['seance' => $seance->id]) }}" class="flex items-center gap-2 rounded bg-base-300 p-2 text-sm">
                                    <span class="inline-block h-3 w-3 shrink-0 rounded" style="background: {{ $colors[$status] }}"></span>
                                    <span class="font-semibold">{{ $seance->started_at->format('H:i') }}</span>
                                    <span>{{ $seance->name }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @empty
            <p class="text-sm text-base-content/60">Aucun lieu.</p>
        @endforelse
    </div>
</x-app-layout>
