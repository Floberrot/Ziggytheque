<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum GenreEnum: string
{
    case Shonen = 'shonen';
    case Shojo = 'shojo';
    case Seinen = 'seinen';
    case Josei = 'josei';
    case Kodomomuke = 'kodomomuke';
    case Isekai = 'isekai';
    case Fantasy = 'fantasy';
    case Action = 'action';
    case Romance = 'romance';
    case Horror = 'horror';
    case SciFi = 'sci_fi';
    case SliceOfLife = 'slice_of_life';
    case Sports = 'sports';
    case Other = 'other';
}
