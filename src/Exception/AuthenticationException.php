<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 401. The API key is missing, wrong or revoked.
 */
class AuthenticationException extends CheckslipException {}
