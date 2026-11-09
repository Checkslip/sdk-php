<?php

declare(strict_types=1);

namespace Checkslip\Model;

use Checkslip\Internal\Arr;

/**
 * A newly created endpoint and its signing secret. The secret is shown only
 * once, in this response: store it somewhere safe now (not in your code).
 */
final class EndpointWithSecret
{
    public function __construct(
        public readonly Endpoint $endpoint,
        /** "whsec_..." — pass it to {@see \Checkslip\Webhook\Webhook}. */
        public readonly string $secret,
    ) {}

    /**
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        return new self(Endpoint::fromArray($d), Arr::str($d, 'secret'));
    }
}
