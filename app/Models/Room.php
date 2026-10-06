<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'name', 'purpose', 'status', 'settings', 'owner_id'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RoomStatus::Active,
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RoomStatus::Active);
    }
}
