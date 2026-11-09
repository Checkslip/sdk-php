<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 5xx. Something failed on the Checkslip side or at the bank.
 */
class ServerException extends CheckslipException {}
