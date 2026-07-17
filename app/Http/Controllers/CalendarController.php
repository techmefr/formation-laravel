<?php

namespace App\Http\Controllers;

use App\Models\Seance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isCoach = $user->hasRole('coach');

        $query = Seance::with('place')
            ->withCount(['participants as registered_count' => fn ($q) => $q->where('seance_user.status', 'registered')]);

        if ($isCoach) {
            $query->where('coach_id', $user->id);
        } else {
            $agency = $request->query('agency', (string) ($user->agency_id ?? 'all'));

            if ($agency !== 'all') {
                $query->where(function ($q) use ($agency) {
                    $q->whereHas('place', fn ($place) => $place->where('type', 'external'))
                        ->orWhere('place_id', $agency);
                });
            }

            if ($request->boolean('mine')) {
                $query->whereHas('participants', fn ($participants) => $participants->whereKey($user->id));
            }
        }

        $myStatuses = DB::table('seance_user')
            ->where('user_id', $user->id)
            ->pluck('status', 'seance_id');

        $events = $query->get()->map(function (Seance $seance) use ($myStatuses) {
            $status = $this->statusFor($seance, $myStatuses[$seance->id] ?? null);

            return [
                'id' => $seance->id,
                'title' => $this->marker($status).' '.$seance->name.' · '.$seance->place->name,
                'start' => $seance->started_at->toIso8601String(),
                'end' => $seance->ended_at?->toIso8601String(),
                'url' => route('seances.show', ['seance' => $seance->id]),
                'color' => $this->color($status),
            ];
        });

        return response()->json($events);
    }

    private function statusFor(Seance $seance, ?string $mine): string
    {
        if ($seance->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($mine === 'registered') {
            return 'registered';
        }

        if ($mine === 'waitlist') {
            return 'waitlist';
        }

        $isFull = $seance->max_participants !== null
            && $seance->registered_count >= $seance->max_participants;

        return $isFull ? 'full' : 'available';
    }

    private function color(string $status): string
    {
        return match ($status) {
            'registered' => '#22c55e',
            'waitlist' => '#f59e0b',
            'full' => '#c8102e',
            'cancelled' => '#6b7280',
            default => '#0ea5e9',
        };
    }

    private function marker(string $status): string
    {
        return match ($status) {
            'registered' => '✓',
            'waitlist' => '⏳',
            'full' => '●',
            'cancelled' => '⊘',
            default => '○',
        };
    }
}
