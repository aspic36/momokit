<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function str_value(mixed $value): ?string
{
    if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
        return null;
    }

    $string = trim((string) $value);

    return $string === '' ? null : $string;
}
