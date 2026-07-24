<?php

use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.edit');

$seance = ($seance ?? null);

if ($seance !== null && ! $seance instanceof Seance) {
    $seance = Seance::findOrFail($seance);
}

$places = Place::orderBy('name')->get();
$coaches = User::role('coach')->orderBy('name')->get();
$isStaff = auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;

$startedAt = old('started_at', $seance?->started_at?->format('Y-m-d\TH:i'));
$endedAt = old('ended_at', $seance?->ended_at?->format('Y-m-d\TH:i'));

?>

<x-app-layout title="Modifier la séance">
    @cannot('update', $seance)
        <section class="panel mx-auto max-w-2xl p-6">
            <p class="text-sm text-base-content/70">Vous n'avez pas le droit de modifier cette séance.</p>
        </section>
    @else
    <section class="panel mx-auto max-w-2xl p-6">
        <a href="{{ route('seances.show', ['seance' => $seance?->id]) }}" class="mb-4 inline-block text-sm text-base-content/70 hover:text-base-content">← Retour</a>
        <h1 class="mb-5 text-2xl font-extrabold">Modifier la séance</h1>

        <form method="POST" action="{{ route('seances.update', ['seance' => $seance?->id]) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
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

            @if ($seance && $seance->getMedia('files')->isNotEmpty())
                <div class="text-sm">
                    <span class="font-semibold">Fichiers actuels :</span>
                    <ul class="mt-1 flex flex-col gap-1">
                        @foreach ($seance->getMedia('files') as $media)
                            <li><a href="{{ $media->getUrl() }}" target="_blank" class="text-primary hover:underline">{{ $media->file_name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Ajouter des fichiers (optionnel)</span>
                <input type="file" name="files[]" multiple class="file-input file-input-bordered w-full">
                @error('files.*') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <div class="mt-2 flex gap-2">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('seances.show', ['seance' => $seance?->id]) }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </section>
    @endcannot
</x-app-layout>
