<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSeanceRequest;
use App\Http\Requests\UpdateSeanceRequest;
use App\Models\Seance;
use App\Services\SeanceService;
use Illuminate\Http\RedirectResponse;

class SeanceController extends Controller
{
    public function __construct(private SeanceService $seances) {}

    public function store(StoreSeanceRequest $request): RedirectResponse
    {
        $this->seances->create($request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance créée.']);
    }

    public function update(UpdateSeanceRequest $request, Seance $seance): RedirectResponse
    {
        $this->seances->update($seance, $request->validated());

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance modifiée.']);
    }

    public function cancel(Seance $seance): RedirectResponse
    {
        $this->authorize('cancel', $seance);
        $this->seances->cancel($seance);

        return back()->with('notification', ['type' => 'success', 'message' => 'Séance annulée.']);
    }

    public function destroy(Seance $seance): RedirectResponse
    {
        $this->authorize('delete', $seance);
        $this->seances->delete($seance);

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance supprimée.']);
    }
}
