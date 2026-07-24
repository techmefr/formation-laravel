<?php

namespace Functional\Seances\Models;

use Database\Factories\SeanceFactory;
use Functional\Places\Models\Place;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Lomkit\Access\Controls\HasControl;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $name
 * @property int|null $max_participants
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $cancelled_at
 * @property-read User|null $coach
 * @property-read Place|null $place
 * @property-read int|null $registered_count
 */
class Seance extends Model implements HasMedia
{
    /** @use HasFactory<SeanceFactory> */
    use HasControl, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'coach_id',
        'place_id',
        'started_at',
        'ended_at',
        'max_participants',
        'recurrence',
        'recurrence_until',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'recurrence_until' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status', 'position')->withTimestamps();
    }

    public function isFull(): bool
    {
        if ($this->max_participants === null) {
            return false;
        }

        return $this->participants()->wherePivot('status', 'registered')->count() >= $this->max_participants;
    }
}
