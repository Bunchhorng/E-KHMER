<?php

declare(strict_types=1);

namespace App\Services;

class OrderNumberGenerator
{
    public const STRATEGY = 'application-year-secure-random-12-digits';

    /**
     * Generate a candidate; the database unique index decides uniqueness.
     */
    public function next(): string
    {
        return 'SV-'.now()->format('Y').'-'.$this->randomSuffix();
    }

    public function forShop(string $parentNumber, int $position, int $attempt): string
    {
        $number = $parentNumber.'-'.$position;

        return $attempt === 1 ? $number : $number.'-'.$this->randomSuffix();
    }

    private function randomSuffix(): string
    {
        return str_pad((string) random_int(0, 999_999_999_999), 12, '0', STR_PAD_LEFT);
    }
}
