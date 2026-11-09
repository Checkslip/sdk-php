<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * The request never got an answer: DNS, TLS, connection or timeout failure.
 */
class ConnectionException extends CheckslipException {}
