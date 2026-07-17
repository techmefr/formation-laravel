<?php

namespace App\Http\Requests;

use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seance = $this->route('seance');

        return $seance instanceof Seance && ($this->user()?->can('update', $seance) ?? false);
    }

    protected function prepareForValidation(): void
    {
        /** @var User $user */
        $user = $this->user();

        if ($user->hasRole('coach')) {
            $this->merge(['coach_id' => $user->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'place_id' => ['required', 'exists:places,id'],
            'coach_id' => ['required', 'exists:users,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
