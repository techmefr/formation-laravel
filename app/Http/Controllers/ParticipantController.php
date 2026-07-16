<?php

namespace App\Http\Controllers;

use App\Models\Seance;
use App\Models\User;
use App\Services\InscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function __construct(private InscriptionService $inscriptions) {}

    public function store(Request $request, Seance $seance): RedirectResponse
    {
        $this->authorize('manageParticipants', $seance);

        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $this->inscriptions->register($seance, User::findOrFail($data['user_id']));

        return back()->with('notification', ['type' => 'success', 'message' => 'Participant inscrit.']);
    }

    public function destroy(Seance $seance, User $user): RedirectResponse
    {
        $this->authorize('manageParticipants', $seance);
        $this->inscriptions->unregister($seance, $user);

        return back()->with('notification', ['type' => 'success', 'message' => 'Participant désinscrit.']);
    }
}
