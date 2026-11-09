<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * HTTP 410. The record existed but has expired, such as an export file past its expires_at.
 */
class GoneException extends CheckslipException {}
