<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'desc', 'instructions', 'skills', 'allowed_tools', 'expected_inputs', 'outputs', 'default_combo', 'version', 'is_builtin'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'allowed_tools' => 'array',
            'expected_inputs' => 'array',
            'outputs' => 'array',
            'is_builtin' => 'boolean',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function scopeBuiltin(Builder $query): Builder
    {
        return $query->where('is_builtin', true);
    }
}
