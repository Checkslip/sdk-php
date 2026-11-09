<?php

declare(strict_types=1);

namespace Checkslip\Tests;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class NetworkError extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('Connection timed out');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
