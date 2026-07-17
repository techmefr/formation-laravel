<?php

namespace App\Http\Controllers;

use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Http\RedirectResponse;

class InscriptionController extends Controller
{
    public function __construct(private InscriptionService $inscriptions) {}

    public function store(Seance $seance): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->inscriptions->register($seance, $user);

        $notification = match ($result) {
            'registered' => ['type' => 'success', 'message' => 'Inscription enregistrée.'],
            'waitlist' => ['type' => 'success', 'message' => "Séance complète : vous êtes en liste d'attente."],
            'conflict' => ['type' => 'error', 'message' => 'Vous avez déjà une séance sur ce créneau horaire.'],
            default => ['type' => 'error', 'message' => 'Vous êtes déjà inscrit à cette séance.'],
        };

        return back()->with('notification', $notification);
    }

    public function destroy(Seance $seance): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->inscriptions->unregister($seance, $user);

        return back()->with('notification', ['type' => 'success', 'message' => 'Désinscription enregistrée.']);
    }
}
