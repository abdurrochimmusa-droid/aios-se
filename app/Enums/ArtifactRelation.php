<?php

namespace App\Enums;

enum ArtifactRelation: string
{
    case DerivedFrom = 'derived_from';
    case Refines = 'refines';
    case Tests = 'tests';
}
