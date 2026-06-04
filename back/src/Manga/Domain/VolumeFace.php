<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum VolumeFace: string
{
    case Cover = 'cover';
    case Spine = 'spine';
    case Back = 'back';
}
