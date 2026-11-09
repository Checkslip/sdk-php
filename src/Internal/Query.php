<?php

declare(strict_types=1);

namespace Checkslip\Internal;

/**
 * Builds a query string the way the API reads it.
 *
 * - null values are left out
 * - bool becomes "true" / "false"
 * - DateTimeInterface becomes RFC 3339
 * - a list repeats the key (outcome=A&outcome=B); join it yourself first when
 *   the API wants one comma-separated value
 *
 * @internal
 */
final class Query
{
    /**
     * @param array<string, scalar|\DateTimeInterface|list<string>|null> $params
     */
    public static function build(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            foreach (\is_array($value) ? $value : [$value] as $one) {
                $parts[] = rawurlencode($key) . '=' . rawurlencode(self::scalar($one));
            }
        }

        return implode('&', $parts);
    }

    /**
     * Formats a date-time for a query parameter. Strings pass through unchanged.
     */
    public static function dateTime(\DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DATE_RFC3339) : $value;
    }

    /**
     * Formats a calendar date ("2026-11-01"). Strings pass through unchanged.
     */
    public static function date(\DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    /**
     * Joins enum cases or strings with commas, for parameters sent as one value.
     *
     * @param list<\BackedEnum|string>|null $values
     */
    public static function csv(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        return implode(',', self::strings($values));
    }

    /**
     * @param list<\BackedEnum|string>|null $values
     *
     * @return list<string>|null
     */
    public static function list(?array $values): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        return self::strings($values);
    }

    /**
     * One value or several, for a parameter the API reads as a repeated key
     * (style=form, explode=true). Accepting both keeps a single filter
     * readable while a list stays possible.
     *
     * @param \BackedEnum|string|int|list<\BackedEnum|string|int>|null $value
     *
     * @return list<string>|null
     */
    public static function many(\BackedEnum|string|int|array|null $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $values = \is_array($value) ? array_values($value) : [$value];
        if ($values === []) {
            return null;
        }

        return array_map(
            static fn(\BackedEnum|string|int $one): string => $one instanceof \BackedEnum ? (string) $one->value : (string) $one,
            $values,
        );
    }

    /**
     * @param list<\BackedEnum|string> $values
     *
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        return array_map(static fn(\BackedEnum|string $v): string => $v instanceof \BackedEnum ? (string) $v->value : $v, $values);
    }

    private static function scalar(mixed $v): string
    {
        return match (true) {
            \is_bool($v) => $v ? 'true' : 'false',
            $v instanceof \DateTimeInterface => $v->format(\DATE_RFC3339),
            \is_scalar($v) => (string) $v,
            default => throw new \InvalidArgumentException('Query values must be scalars, dates or lists of strings.'),
        };
    }
}
