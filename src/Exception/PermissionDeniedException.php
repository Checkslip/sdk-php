<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 403. The API key does not have the scope this call needs.
 */
class PermissionDeniedException extends CheckslipException {}
