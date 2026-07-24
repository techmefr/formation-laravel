<?php

namespace Functional\Seances\Http\Controllers;

use App\Http\Controllers\Controller;
use Functional\Seances\Http\Requests\StoreSeanceRequest;
use Functional\Seances\Http\Requests\UpdateSeanceRequest;
use Functional\Seances\Models\Seance;
use Functional\Seances\Services\SeanceService;
use Illuminate\Http\RedirectResponse;

class SeanceController extends Controller
{
    public function __construct(private SeanceService $seances) {}

    public function store(StoreSeanceRequest $request): RedirectResponse
    {
        $seance = $this->seances->create($request->safe()->except('files'));
        $this->seances->attachFiles($seance, $request->file('files', []));

        return redirect()->route('seances.index')
            ->with('notification', ['type' => 'success', 'message' => 'Séance créée.']);
    }

    public function update(UpdateSeanceRequest $request, Seance $seance): RedirectResponse
    {
        $this->seances->update($seance, $request->safe()->except('files'));
        $this->seances->attachFiles($seance, $request->file('files', []));

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
