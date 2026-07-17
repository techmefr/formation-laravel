<?php

use App\Models\Place;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.index');

$user = auth()->user();
$isCoach = $user?->hasRole('coach') ?? false;

$agencies = Place::where('type', 'agency')->orderBy('name')->get();
$myAgencyId = $user?->agency_id;
$defaultAgency = $myAgencyId !== null ? (string) $myAgencyId : 'all';

?>

<x-app-layout title="Séances">
    <section class="panel mb-6 p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold">{{ $isCoach ? 'Mes cours' : 'Séances' }}</h1>
                <p class="text-sm text-base-content/70">
                    {{ $isCoach ? 'Les cours que vous animez' : 'Calendrier des cours de sport' }}
                </p>
            </div>

            @unless ($isCoach)
                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/70">Agence</span>
                        <select id="agency-filter" class="select select-sm select-bordered">
                            <option value="all">Toutes les agences</option>
                            @foreach ($agencies as $agency)
                                <option value="{{ $agency->id }}" @selected($defaultAgency === (string) $agency->id)>{{ $agency->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="flex cursor-pointer items-center gap-2 pt-4 text-sm">
                        <input type="checkbox" id="mine-filter" class="checkbox checkbox-sm checkbox-primary">
                        Seulement mes inscriptions
                    </label>
                </div>
            @endunless

            @can('create', App\Models\Seance::class)
                <a href="{{ route('seances.create') }}" class="btn btn-primary btn-sm">+ Nouvelle séance</a>
            @endcan
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 pt-4 text-xs font-medium text-base-content/85">
            <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded" style="background:#22c55e"></span> Disponible</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded" style="background:#c8102e"></span> Complet</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded" style="background:#0ea5e9"></span> Inscrit</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded" style="background:#eab308"></span> Liste d'attente</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded" style="background:#6b7280"></span> Annulée</span>
        </div>
    </section>

    <section class="panel p-4 sm:p-5">
        <div id="calendar"
             data-events-url="{{ route('calendar.events') }}"
             data-agency="{{ $defaultAgency }}"
             data-initial-view="timeGridWeek"></div>
    </section>

    @push('scripts')
        @vite(['resources/js/calendar.js'])
    @endpush
</x-app-layout>
