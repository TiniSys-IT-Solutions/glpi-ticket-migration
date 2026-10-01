<?php

namespace GlpiPlugin\Ticketmigration\Plan;

final class DateNormalizer
{
    private const FORMATS = [
        '!d/m/Y H:i:s',
        '!d/m/Y H:i',
        '!d/m/Y',
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!Y-m-d\\TH:i:sP',
        '!Y-m-d\\TH:i:s',
    ];

    public function normalize(string $value): ?string
    {
        $value = trim($value);
        foreach (self::FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
