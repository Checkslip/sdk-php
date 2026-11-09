<?php

declare(strict_types=1);

namespace Checkslip\Webhook;

use Checkslip\Exception\WebhookVerificationException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Checks that a webhook message came from Checkslip and was not changed.
 *
 * Checkslip signs messages the Standard Webhooks way
 * (https://www.standardwebhooks.com):
 *
 * - webhook-id: the message id, kept when the same message is sent again
 * - webhook-timestamp: Unix seconds when this attempt was signed
 * - webhook-signature: one or more "v1,<base64>" values separated by spaces,
 *   each base64(HMAC-SHA256(key, "{id}.{timestamp}.{body}")). The key is the
 *   secret after "whsec_", base64-decoded. During the 24 hours after a secret
 *   rotation the header carries one signature per secret.
 *
 * Always pass the raw request body, byte for byte, before any JSON decoding.
 *
 * ```php
 * $webhook = new Webhook(getenv('CHECKSLIP_WEBHOOK_SECRET'));
 * $event = $webhook->verify(file_get_contents('php://input'), getallheaders());
 * ```
 */
final class Webhook
{
    /** Default allowed clock difference, in seconds, between Checkslip and your server. */
    public const DEFAULT_TOLERANCE = 300;

    /** @var list<string> Raw HMAC keys. */
    private readonly array $keys;

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /**
     * @param string|list<string> $secret One "whsec_..." secret, or several while you switch to a rotated one.
     * @param int $tolerance Reject messages signed more than this many seconds away from now. Replay protection.
     * @param (\Closure(): int)|null $clock Returns the current Unix time. For tests.
     */
    public function __construct(string|array $secret, private readonly int $tolerance = self::DEFAULT_TOLERANCE, ?\Closure $clock = null)
    {
        $secrets = \is_array($secret) ? $secret : [$secret];
        if ($secrets === []) {
            throw new \InvalidArgumentException('Give at least one webhook secret.');
        }
        $this->keys = array_map(self::decodeSecret(...), $secrets);
        $this->clock = $clock ?? static fn(): int => time();
    }

    /**
     * Checks the signature and timestamp, then decodes the message.
     *
     * @param array<string, string|list<string>> $headers Request headers in any letter case, as from
     *                                                    getallheaders(), a framework, or $_SERVER (HTTP_WEBHOOK_ID).
     *
     * @throws WebhookVerificationException when the message must not be trusted
     */
    public function verify(string $payload, array $headers): WebhookEvent
    {
        $id = self::header($headers, 'webhook-id');
        $timestamp = self::header($headers, 'webhook-timestamp');
        $signature = self::header($headers, 'webhook-signature');
        if ($id === null || $timestamp === null || $signature === null) {
            throw new WebhookVerificationException('Missing webhook-id, webhook-timestamp or webhook-signature header.');
        }

        if (!ctype_digit($timestamp)) {
            throw new WebhookVerificationException('The webhook-timestamp header is not a Unix timestamp.');
        }
        $ts = (int) $timestamp;
        $now = ($this->clock)();
        if (abs($now - $ts) > $this->tolerance) {
            throw new WebhookVerificationException(\sprintf(
                'The message timestamp is %d seconds away from now, more than the %d allowed. Check your server clock, or this may be a replay.',
                abs($now - $ts),
                $this->tolerance,
            ));
        }

        if (!$this->signatureMatches($signature, $id, $ts, $payload)) {
            throw new WebhookVerificationException('No signature in webhook-signature matches this secret.');
        }

        try {
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new WebhookVerificationException('The signed body is not JSON.', previous: $e);
        }
        if (!\is_array($decoded)) {
            throw new WebhookVerificationException('The signed body is not a JSON object.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            return WebhookEvent::fromArray($decoded, $id);
        } catch (\Checkslip\Exception\UnexpectedResponseException $e) {
            throw new WebhookVerificationException('The signed body is not a Checkslip message: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Same as {@see verify()}, reading body and headers from a PSR-7 server request.
     */
    public function verifyRequest(ServerRequestInterface $request): WebhookEvent
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $headers = [];
        foreach (['webhook-id', 'webhook-timestamp', 'webhook-signature'] as $name) {
            if ($request->hasHeader($name)) {
                $headers[$name] = $request->getHeaderLine($name);
            }
        }

        return $this->verify($body->getContents(), $headers);
    }

    /**
     * Builds the webhook-signature value for a body, one "v1,..." per secret.
     * Useful for testing your own handler.
     */
    public function sign(string $id, int $timestamp, string $payload): string
    {
        return implode(' ', array_map(
            static fn(string $key): string => 'v1,' . base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $key, true)),
            $this->keys,
        ));
    }

    private function signatureMatches(string $header, string $id, int $ts, string $payload): bool
    {
        $signed = "{$id}.{$ts}.{$payload}";
        $given = [];
        foreach (preg_split('/\s+/', trim($header)) ?: [] as $part) {
            [$version, $sig] = array_pad(explode(',', $part, 2), 2, '');
            if ($version === 'v1' && $sig !== '') {
                $given[] = $sig;
            }
        }
        foreach ($this->keys as $key) {
            $expected = base64_encode(hash_hmac('sha256', $signed, $key, true));
            foreach ($given as $sig) {
                if (hash_equals($expected, $sig)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function decodeSecret(string $secret): string
    {
        $secret = trim($secret);
        if (!str_starts_with($secret, 'whsec_')) {
            throw new \InvalidArgumentException('A Checkslip webhook secret starts with "whsec_".');
        }
        $key = base64_decode(substr($secret, 6), true);
        if ($key === false || $key === '') {
            throw new \InvalidArgumentException('The webhook secret is not valid base64 after "whsec_".');
        }

        return $key;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0 || (string) $key === $server) {
                $value = \is_array($value) ? implode(' ', $value) : $value;
                $value = trim($value);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }
}
