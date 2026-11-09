<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * The API answered with something the SDK could not read, such as a body that is not JSON.
 */
class UnexpectedResponseException extends CheckslipException {}
