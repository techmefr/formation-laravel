<?php

use Functional\Seances\Models\Seance;
use Functional\Users\Models\User;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);
name('seances.show');

$seance = ($seance ?? null);

if ($seance !== null && ! $seance instanceof Seance) {
    $seance = Seance::findOrFail($seance);
}

$registered = collect();
$waitlist = collect();
$available = collect();
$mine = null;

if ($seance) {
    $seance->load(['coach', 'place']);

    $registered = $seance->participants()->wherePivot('status', 'registered')->orderByPivot('position')->get();
    $waitlist = $seance->participants()->wherePivot('status', 'waitlist')->orderByPivot('position')->get();

    if ($user = auth()->user()) {
        $participant = $seance->participants()->whereKey($user->id)->first();
        $mine = $participant?->pivot->status;
    }

    $participantIds = $registered->pluck('id')->merge($waitlist->pluck('id'));
    $available = User::whereNotIn('id', $participantIds)->orderBy('name')->get();
}

$isCancelled = $seance?->cancelled_at !== null;
$isFull = $seance?->isFull() ?? false;

?>

<x-app-layout title="{{ $seance?->name }}">
    <a href="{{ route('seances.index') }}" class="mb-4 inline-block text-sm text-base-content/60 hover:text-base-content">← Retour aux séances</a>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="panel p-6">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold">{{ $seance?->name }}</h1>
                        <p class="text-sm text-base-content/60">
                            {{ $seance?->started_at->format('l d/m/Y à H:i') }}
                            @if ($seance?->ended_at)
                                → {{ $seance->ended_at->format('H:i') }}
                            @endif
                        </p>
                    </div>
                    @if ($isCancelled)
                        <span class="badge badge-neutral">Annulée</span>
                    @else
                        <span class="badge {{ $isFull ? 'badge-error' : 'badge-success' }}">
                            {{ $registered->count() }}/{{ $seance?->max_participants ?? '∞' }}
                        </span>
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase text-base-content/50">Coach</div>
                        <div>{{ $seance?->coach?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-base-content/50">Lieu</div>
                        <div>
                            {{ $seance?->place?->name ?? '—' }}
                            @if ($seance?->place)
                                <span class="badge badge-xs {{ $seance->place->type === 'external' ? 'badge-info' : 'badge-primary' }}">{{ $seance->place->type === 'external' ? 'Externe' : 'Agence' }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($seance?->place?->description)
                    <p class="mt-4 text-sm text-base-content/70">{{ $seance->place->description }}</p>
                @endif

                @if ($seance && $seance->getMedia('files')->isNotEmpty())
                    <div class="mt-4">
                        <div class="text-xs uppercase text-base-content/50">Fichiers</div>
                        <ul class="mt-1 flex flex-col gap-1">
                            @foreach ($seance->getMedia('files') as $media)
                                <li><a href="{{ $media->getUrl() }}" target="_blank" class="text-sm text-primary hover:underline">{{ $media->file_name }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-6">
                    @if ($isCancelled)
                        <p class="text-sm text-base-content/60">Cette séance est annulée, les inscriptions sont fermées.</p>
                    @elseif ($mine === 'registered')
                        <form method="POST" action="{{ route('seances.inscription.destroy', $seance) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline">Se désinscrire</button>
                        </form>
                    @elseif ($mine === 'waitlist')
                        <form method="POST" action="{{ route('seances.inscription.destroy', $seance) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-warning">Quitter la file d'attente</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('seances.inscription.store', $seance) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ $isFull ? "S'inscrire en file d'attente" : "S'inscrire" }}</button>
                        </form>
                    @endif
                </div>

                @canany(['update', 'cancel', 'delete'], $seance)
                    <div class="mt-6 flex flex-wrap gap-2 border-t border-white/10 pt-4">
                        @can('update', $seance)
                            <a href="{{ route('seances.edit', ['seance' => $seance->id]) }}" class="btn btn-outline btn-sm">Modifier</a>
                        @endcan
                        @can('cancel', $seance)
                            @unless ($isCancelled)
                                <form method="POST" action="{{ route('seances.cancel', ['seance' => $seance->id]) }}" onsubmit="return confirm('Annuler cette séance ?')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-warning btn-sm">Annuler la séance</button>
                                </form>
                            @endunless
                        @endcan
                        @can('delete', $seance)
                            <form method="POST" action="{{ route('seances.destroy', ['seance' => $seance->id]) }}" onsubmit="return confirm('Supprimer définitivement cette séance ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-error btn-sm">Supprimer</button>
                            </form>
                        @endcan
                    </div>
                @endcanany
            </div>
        </div>

        <div>
            <div class="panel p-4">
                <h2 class="mb-3 font-bold">Participants ({{ $registered->count() }})</h2>
                <ul class="flex flex-col gap-1">
                    @forelse ($registered as $participant)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ $participant->name }}</span>
                            @can('manageParticipants', $seance)
                                <form method="POST" action="{{ route('seances.participants.destroy', [$seance, $participant]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">retirer</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-sm text-base-content/50">Aucun inscrit.</li>
                    @endforelse
                </ul>

                @if ($waitlist->isNotEmpty())
                    <h3 class="mt-4 mb-2 text-sm font-bold text-warning">File d'attente ({{ $waitlist->count() }})</h3>
                    <ol class="flex flex-col gap-1">
                        @foreach ($waitlist as $participant)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span>{{ $loop->iteration }}. {{ $participant->name }}</span>
                                @can('manageParticipants', $seance)
                                    <form method="POST" action="{{ route('seances.participants.destroy', [$seance, $participant]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">retirer</button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ol>
                @endif

                @can('manageParticipants', $seance)
                    @if (! $isCancelled)
                        <form method="POST" action="{{ route('seances.participants.store', $seance) }}" class="mt-4 flex flex-col gap-2 border-t border-white/10 pt-4">
                            @csrf
                            <label class="text-xs uppercase text-base-content/50">Inscrire quelqu'un</label>
                            <select name="user_id" class="select select-sm select-bordered" required>
                                <option value="">Choisir un utilisateur…</option>
                                @foreach ($available as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Inscrire</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
