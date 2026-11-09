<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 413, 415 or 422. The API understood the request and could not act on it.
 *
 * For a lookup this covers INVALID_QR, QR_TAMPERED, TOO_OLD, INVALID_FILE and
 * MULTIPLE_SLIP_QR. None of these use a credit.
 */
class UnprocessableEntityException extends CheckslipException {}
