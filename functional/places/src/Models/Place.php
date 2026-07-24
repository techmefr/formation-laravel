<?php

namespace Functional\Places\Models;

use Database\Factories\PlaceFactory;
use Functional\Seances\Models\Seance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string|null $code
 */
class Place extends Model
{
    /** @use HasFactory<PlaceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'type', 'code'];

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }
}
