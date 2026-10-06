<?php

namespace App\Models;

use App\Enums\ArtifactRelation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_artifact_id', 'to_artifact_id', 'relation'])]
class ArtifactLink extends Model
{
    protected function casts(): array
    {
        return [
            'relation' => ArtifactRelation::class,
        ];
    }

    public function fromArtifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class, 'from_artifact_id');
    }

    public function toArtifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class, 'to_artifact_id');
    }
}
