<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope', 'scope_id', 'key_name', 'value'])]
#[Hidden(['value'])]
class Secret extends Model
{
    protected $attributes = [
        'scope' => 'global',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public function scopeForScope(Builder $query, string $scope, ?int $scopeId = null): Builder
    {
        return $query->where('scope', $scope)->where('scope_id', $scopeId);
    }
}
