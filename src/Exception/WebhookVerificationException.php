<?php

declare(strict_types=1);

namespace Checkslip\Exception;

/**
 * A webhook message failed the signature or timestamp check. Do not trust its body.
 */
class WebhookVerificationException extends CheckslipException {}
