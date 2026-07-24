<?php

namespace Functional\Seances\Http\Controllers;

use App\Http\Controllers\Controller;
use Functional\Seances\Models\Seance;
use Functional\Seances\Services\InscriptionService;
use Functional\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function __construct(private InscriptionService $inscriptions) {}

    public function store(Request $request, Seance $seance): RedirectResponse
    {
        $this->authorize('manageParticipants', $seance);

        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $result = $this->inscriptions->register($seance, User::findOrFail($data['user_id']));

        $notification = match ($result) {
            'registered' => ['type' => 'success', 'message' => 'Participant inscrit.'],
            'waitlist' => ['type' => 'success', 'message' => "Séance complète : participant en liste d'attente."],
            'conflict' => ['type' => 'error', 'message' => 'Ce participant a déjà une séance sur ce créneau horaire.'],
            default => ['type' => 'error', 'message' => 'Ce participant est déjà inscrit à cette séance.'],
        };

        return back()->with('notification', $notification);
    }

    public function destroy(Seance $seance, User $user): RedirectResponse
    {
        $this->authorize('manageParticipants', $seance);
        $this->inscriptions->unregister($seance, $user);

        return back()->with('notification', ['type' => 'success', 'message' => 'Participant désinscrit.']);
    }
}
