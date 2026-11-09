<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 400. The request was malformed, for example VALIDATION_FAILED or IDEMPOTENCY_KEY_INVALID. Field problems are in $errors.
 */
class BadRequestException extends CheckslipException {}
