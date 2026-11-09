<?php

declare(strict_types=1);

namespace Checkslip\Internal;

use Checkslip\Exception\UnexpectedResponseException;

/**
 * Typed reads from a decoded JSON object. A missing or wrong-typed required
 * field throws UnexpectedResponseException, so models never hold a half-read value.
 *
 * @internal
 */
final class Arr
{
    /**
     * @param array<array-key, mixed> $d
     */
    public static function str(array $d, string $key): string
    {
        $v = $d[$key] ?? null;
        if (!\is_string($v)) {
            throw self::bad($key, 'string', $v);
        }

        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function strOrNull(array $d, string $key): ?string
    {
        $v = $d[$key] ?? null;
        if ($v !== null && !\is_string($v)) {
            throw self::bad($key, 'string or null', $v);
        }

        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function int(array $d, string $key): int
    {
        $v = $d[$key] ?? null;
        if (!\is_int($v)) {
            throw self::bad($key, 'integer', $v);
        }

        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function intOrNull(array $d, string $key): ?int
    {
        $v = $d[$key] ?? null;
        if ($v !== null && !\is_int($v)) {
            throw self::bad($key, 'integer or null', $v);
        }

        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function bool(array $d, string $key): bool
    {
        $v = $d[$key] ?? null;
        if (!\is_bool($v)) {
            throw self::bad($key, 'boolean', $v);
        }

        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function boolOrNull(array $d, string $key): ?bool
    {
        $v = $d[$key] ?? null;
        if ($v !== null && !\is_bool($v)) {
            throw self::bad($key, 'boolean or null', $v);
        }

        return $v;
    }

    /**
     * Reads an RFC 3339 date-time ("2026-10-29T10:41:02+07:00") or a date ("2026-10-29").
     *
     * @param array<array-key, mixed> $d
     */
    public static function date(array $d, string $key): \DateTimeImmutable
    {
        $v = self::str($d, $key);
        try {
            return new \DateTimeImmutable($v);
        } catch (\Exception $e) {
            throw new UnexpectedResponseException(\sprintf('Field "%s" is not a valid date: %s', $key, $v), previous: $e);
        }
    }

    /**
     * @param array<array-key, mixed> $d
     */
    public static function dateOrNull(array $d, string $key): ?\DateTimeImmutable
    {
        if (!isset($d[$key])) {
            return null;
        }

        return self::date($d, $key);
    }

    /**
     * @param array<array-key, mixed> $d
     *
     * @return array<string, mixed>
     */
    public static function obj(array $d, string $key): array
    {
        $v = $d[$key] ?? null;
        if (!\is_array($v)) {
            throw self::bad($key, 'object', $v);
        }

        /** @var array<string, mixed> $v */
        return $v;
    }

    /**
     * @param array<array-key, mixed> $d
     *
     * @return array<string, mixed>|null
     */
    public static function objOrNull(array $d, string $key): ?array
    {
        if (!isset($d[$key])) {
            return null;
        }

        return self::obj($d, $key);
    }

    /**
     * A JSON array of objects. A missing key reads as an empty list.
     *
     * @param array<array-key, mixed> $d
     *
     * @return list<array<string, mixed>>
     */
    public static function objects(array $d, string $key): array
    {
        $v = $d[$key] ?? [];
        if (!\is_array($v)) {
            throw self::bad($key, 'array', $v);
        }
        $out = [];
        foreach ($v as $item) {
            if (!\is_array($item)) {
                throw self::bad($key . '[]', 'object', $item);
            }
            /** @var array<string, mixed> $item */
            $out[] = $item;
        }

        return $out;
    }

    /**
     * A JSON array of strings. A missing key reads as an empty list.
     *
     * @param array<array-key, mixed> $d
     *
     * @return list<string>
     */
    public static function strings(array $d, string $key): array
    {
        $v = $d[$key] ?? [];
        if (!\is_array($v)) {
            throw self::bad($key, 'array', $v);
        }
        $out = [];
        foreach ($v as $item) {
            if (!\is_string($item)) {
                throw self::bad($key . '[]', 'string', $item);
            }
            $out[] = $item;
        }

        return $out;
    }

    private static function bad(string $key, string $want, mixed $got): UnexpectedResponseException
    {
        return new UnexpectedResponseException(\sprintf(
            'Field "%s" should be %s, got %s.',
            $key,
            $want,
            get_debug_type($got),
        ));
    }
}
