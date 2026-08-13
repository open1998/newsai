<?php

namespace App\Enums;

enum Language: string
{
    case En = 'en';
    case Ta = 'ta';
    case Si = 'si';

    /**
     * Get the human-readable label for the language.
     */
    public function label(): string
    {
        return match ($this) {
            Language::En => 'English',
            Language::Ta => 'Tamil',
            Language::Si => 'Sinhala',
        };
    }
}
