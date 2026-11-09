<?php

declare(strict_types=1);

namespace Checkslip\Internal;

/**
 * @internal
 */
final class Uuid
{
    /** A random version 4 UUID, used as an Idempotency-Key when the caller gives none. */
    public static function v4(): string
    {
        $b = random_bytes(16);
        $b[6] = \chr((\ord($b[6]) & 0x0F) | 0x40);
        $b[8] = \chr((\ord($b[8]) & 0x3F) | 0x80);
        $h = bin2hex($b);

        return \sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20));
    }
}
