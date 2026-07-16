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
        $this->inscriptions->register($seance, $user);

        return back()->with('notification', ['type' => 'success', 'message' => 'Inscription enregistrée.']);
    }

    public function destroy(Seance $seance): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->inscriptions->unregister($seance, $user);

        return back()->with('notification', ['type' => 'success', 'message' => 'Désinscription enregistrée.']);
    }
}
