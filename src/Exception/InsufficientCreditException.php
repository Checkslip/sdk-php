<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 402. No credit left (NO_CREDIT) or the credit has expired (CREDIT_EXPIRED).
 */
class InsufficientCreditException extends CheckslipException {}
