<?php

use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.edit');

$seance = ($seance ?? null);

if ($seance !== null && ! $seance instanceof Seance) {
    $seance = Seance::findOrFail($seance);
}

if ($seance !== null && auth()->user()?->cannot('update', $seance)) {
    abort(403);
}

$places = Place::orderBy('name')->get();
$coaches = User::role('coach')->orderBy('name')->get();
$isStaff = auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;

$startedAt = old('started_at', $seance?->started_at?->format('Y-m-d\TH:i'));
$endedAt = old('ended_at', $seance?->ended_at?->format('Y-m-d\TH:i'));

?>

<x-app-layout title="Modifier la séance">
    <section class="panel mx-auto max-w-2xl p-6">
        <a href="{{ route('seances.show', ['seance' => $seance?->id]) }}" class="mb-4 inline-block text-sm text-base-content/70 hover:text-base-content">← Retour</a>
        <h1 class="mb-5 text-2xl font-extrabold">Modifier la séance</h1>

        <form method="POST" action="{{ route('seances.update', ['seance' => $seance?->id]) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Nom</span>
                <input type="text" name="name" value="{{ old('name', $seance?->name) }}" required class="input input-bordered w-full">
                @error('name') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Lieu</span>
                <select name="place_id" required class="select select-bordered w-full">
                    @foreach ($places as $place)
                        <option value="{{ $place->id }}" @selected(old('place_id', $seance?->place_id) == $place->id)>{{ $place->name }} ({{ $place->type === 'external' ? 'externe' : 'agence' }})</option>
                    @endforeach
                </select>
                @error('place_id') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            @if ($isStaff)
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Coach</span>
                    <select name="coach_id" required class="select select-bordered w-full">
                        @foreach ($coaches as $coach)
                            <option value="{{ $coach->id }}" @selected(old('coach_id', $seance?->coach_id) == $coach->id)>{{ $coach->name }}</option>
                        @endforeach
                    </select>
                    @error('coach_id') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Début</span>
                    <input type="datetime-local" name="started_at" value="{{ $startedAt }}" required class="input input-bordered w-full">
                    @error('started_at') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Fin</span>
                    <input type="datetime-local" name="ended_at" value="{{ $endedAt }}" required class="input input-bordered w-full">
                    @error('ended_at') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Nombre de places (vide = illimité)</span>
                <input type="number" name="max_participants" min="1" value="{{ old('max_participants', $seance?->max_participants) }}" class="input input-bordered w-full">
                @error('max_participants') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <div class="mt-2 flex gap-2">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('seances.show', ['seance' => $seance?->id]) }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </section>
</x-app-layout>
