<?php

declare(strict_types=1);

namespace Checkslip\Internal;

/**
 * @internal
 */
final class Enums
{
    /**
     * Maps a value to its enum case. A value this SDK version does not know yet
     * (the API added it later) comes back as the plain string instead of failing.
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|string
     */
    public static function of(string $enum, string $value): \BackedEnum|string
    {
        return $enum::tryFrom($value) ?? $value;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|string|null
     */
    public static function ofNullable(string $enum, ?string $value): \BackedEnum|string|null
    {
        return $value === null ? null : self::of($enum, $value);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     * @param list<string> $values
     *
     * @return list<T|string>
     */
    public static function list(string $enum, array $values): array
    {
        return array_map(static fn(string $v): \BackedEnum|string => self::of($enum, $v), $values);
    }

    public static function value(\BackedEnum|string $v): string
    {
        return $v instanceof \BackedEnum ? (string) $v->value : $v;
    }
}
