<?php

namespace App\Models;

use App\Enums\CommandStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'raw_input', 'parsed', 'status', 'result'])]
class CommandHistory extends Model
{
    protected $attributes = [
        'status' => CommandStatus::Preview,
    ];

    protected function casts(): array
    {
        return [
            'parsed' => 'array',
            'status' => CommandStatus::class,
            'result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
