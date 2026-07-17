<?php

use App\Models\Place;
use App\Models\Seance;
use App\Models\User;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.create');

if (auth()->user()?->cannot('create', Seance::class)) {
    abort(403);
}

$places = Place::orderBy('name')->get();
$coaches = User::role('coach')->orderBy('name')->get();
$isStaff = auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;

$defaultPlace = old('place_id', request('place_id'));
$defaultStart = old('started_at');
$defaultEnd = old('ended_at');

if ($defaultStart === null && request('date') !== null && request('start') !== null) {
    $defaultStart = request('date').'T'.request('start');
    $defaultEnd = request('date').'T'.\Illuminate\Support\Carbon::parse(request('start'))->addHour()->format('H:i');
}

?>

<x-app-layout title="Nouvelle séance">
    <section class="panel mx-auto max-w-2xl p-6">
        <a href="{{ route('seances.index') }}" class="mb-4 inline-block text-sm text-base-content/70 hover:text-base-content">← Retour</a>
        <h1 class="mb-5 text-2xl font-extrabold">Nouvelle séance</h1>

        <form method="POST" action="{{ route('seances.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Nom</span>
                <input type="text" name="name" value="{{ old('name') }}" required class="input input-bordered w-full">
                @error('name') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Lieu</span>
                <select name="place_id" required class="select select-bordered w-full">
                    <option value="">Choisir un lieu…</option>
                    @foreach ($places as $place)
                        <option value="{{ $place->id }}" @selected((string) $defaultPlace === (string) $place->id)>{{ $place->name }} ({{ $place->type === 'external' ? 'externe' : 'agence' }})</option>
                    @endforeach
                </select>
                @error('place_id') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            @if ($isStaff)
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Coach</span>
                    <select name="coach_id" required class="select select-bordered w-full">
                        <option value="">Choisir un coach…</option>
                        @foreach ($coaches as $coach)
                            <option value="{{ $coach->id }}" @selected(old('coach_id') == $coach->id)>{{ $coach->name }}</option>
                        @endforeach
                    </select>
                    @error('coach_id') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Début</span>
                    <input type="datetime-local" name="started_at" value="{{ $defaultStart }}" required class="input input-bordered w-full">
                    @error('started_at') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-semibold">Fin</span>
                    <input type="datetime-local" name="ended_at" value="{{ $defaultEnd }}" required class="input input-bordered w-full">
                    @error('ended_at') <span class="text-xs text-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Nombre de places (vide = illimité)</span>
                <input type="number" name="max_participants" min="1" value="{{ old('max_participants') }}" class="input input-bordered w-full">
                @error('max_participants') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1">
                <span class="text-sm font-semibold">Fichiers (optionnel)</span>
                <input type="file" name="files[]" multiple class="file-input file-input-bordered w-full">
                @error('files.*') <span class="text-xs text-error">{{ $message }}</span> @enderror
            </label>

            <div class="mt-2 flex gap-2">
                <button type="submit" class="btn btn-primary">Créer la séance</button>
                <a href="{{ route('seances.index') }}" class="btn btn-ghost">Annuler</a>
            </div>
        </form>
    </section>
</x-app-layout>
