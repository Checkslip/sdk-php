<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 409. The request clashes with the current state, for example IDEMPOTENCY_IN_FLIGHT or ROTATION_IN_PROGRESS.
 */
class ConflictException extends CheckslipException {}
