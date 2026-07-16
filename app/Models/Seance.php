<?php

namespace App\Models;

use Database\Factories\SeanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seance extends Model
{
    /** @use HasFactory<SeanceFactory> */
    use HasFactory, SoftDeletes;

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
}
