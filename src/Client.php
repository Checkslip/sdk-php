<?php

declare(strict_types=1);

namespace Checkslip;

use Checkslip\Internal\Retry;
use Checkslip\Internal\Transport;
use Checkslip\Resource\Audit;
use Checkslip\Resource\Banks;
use Checkslip\Resource\Billing;
use Checkslip\Resource\Slips;
use Checkslip\Resource\Usage;
use Checkslip\Resource\Webhooks;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point to the Checkslip API.
 *
 * ```php
 * $client = new Checkslip\Client(apiKey: getenv('CHECKSLIP_API_KEY'));
 * $check = $client->slips->lookup(qr: $qrText);
 * ```
 */
final class Client
{
    public const VERSION = '0.1.1'; // x-release-please-version

    public const DEFAULT_BASE_URL = 'https://api.checkslip.com';

    public readonly Slips $slips;

    public readonly Billing $billing;

    public readonly Usage $usage;

    public readonly Banks $banks;

    public readonly Webhooks $webhooks;

    public readonly Audit $audit;

    /**
     * @param string $apiKey Your secret API key ("csk_..."). Read it from the environment, never from source code.
     * @param string $baseUrl API origin. Change it only for a test server.
     * @param float $timeout Seconds before a request gives up. Applied when the SDK builds the HTTP
     *                       client itself (Guzzle or Symfony HttpClient installed). If you pass
     *                       $httpClient, set the timeout on that client instead.
     * @param int $maxRetries How many times to retry a failed request. 0 turns retries off.
     * @param ClientInterface|null $httpClient Any PSR-18 client. Found automatically when null.
     * @param RequestFactoryInterface|null $requestFactory PSR-17 factory. Found automatically when null.
     * @param StreamFactoryInterface|null $streamFactory PSR-17 factory. Found automatically when null.
     * @param (\Closure(float): void)|null $sleep Replaces the wait between retries. Meant for tests.
     */
    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?string $userAgent = null,
        ?\Closure $sleep = null,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('The API key is empty. Set it from an environment variable such as CHECKSLIP_API_KEY.');
        }
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be greater than 0.');
        }
        $baseUrl = rtrim($baseUrl, '/');
        if (!preg_match('#^https?://[^/]+$#i', $baseUrl)) {
            throw new \InvalidArgumentException('baseUrl must be an origin such as https://api.checkslip.com, without a path.');
        }

        $ua = 'checkslip-php/' . self::VERSION . ' PHP/' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
        $transport = new Transport(
            $apiKey,
            $baseUrl,
            $httpClient ?? self::defaultHttpClient($timeout),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            new Retry($maxRetries),
            $userAgent !== null ? $userAgent . ' ' . $ua : $ua,
            $sleep,
        );

        $this->slips = new Slips($transport);
        $this->billing = new Billing($transport);
        $this->usage = new Usage($transport);
        $this->banks = new Banks($transport);
        $this->webhooks = new Webhooks($transport);
        $this->audit = new Audit($transport);
    }

    /**
     * Builds an HTTP client with the timeout applied when a known one is
     * installed, and falls back to discovery otherwise.
     */
    private static function defaultHttpClient(float $timeout): ClientInterface
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => min(10.0, $timeout),
                'http_errors' => false,
            ]);
        }
        if (class_exists(\Symfony\Component\HttpClient\Psr18Client::class)) {
            return new \Symfony\Component\HttpClient\Psr18Client(
                \Symfony\Component\HttpClient\HttpClient::create(['timeout' => $timeout, 'max_duration' => $timeout]),
            );
        }

        return Psr18ClientDiscovery::find();
    }
}
