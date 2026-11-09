<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * The new signing secret. The old one keeps working until $previousSecretExpiresAt
 * (24 hours), and until then each message carries a signature for both.
 */
final class SecretRotation
{
    public function __construct(
        public readonly string $secret,
        public readonly \DateTimeImmutable $previousSecretExpiresAt,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Arr::str($d, 'secret'), Arr::date($d, 'previous_secret_expires_at'));
    }
}
