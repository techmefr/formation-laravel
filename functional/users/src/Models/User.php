<?php

namespace Functional\Users\Models;

use Database\Factories\UserFactory;
use Functional\Places\Models\Place;
use Functional\Seances\Models\Seance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int|null $agency_id
 * @property-read Place|null $agency
 */
#[Fillable(['name', 'email', 'password', 'agency_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Force spatie/laravel-permission to always resolve roles/permissions on the
     * "web" guard, even when the request is authenticated via the "api" (JWT) guard.
     * Same user, same roles — the guard is just how they proved who they are.
     */
    public function guardName(): string
    {
        return 'web';
    }

    public function seances(): BelongsToMany
    {
        return $this->belongsToMany(Seance::class)->withPivot('status', 'position')->withTimestamps();
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'agency_id');
    }
}
