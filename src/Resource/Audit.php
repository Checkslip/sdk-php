<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Transport;

/**
 * The account's audit log: activity, the request log and evidence files.
 */
final class Audit extends AbstractResource
{
    /** Account activity, such as an API key created or a member removed. */
    public readonly AuditEvents $events;

    /** Requests this account sent. */
    public readonly AuditRequests $requests;

    /** Evidence files for an auditor, a bank or a court. */
    public readonly AuditExports $exports;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
        $this->events = new AuditEvents($transport);
        $this->requests = new AuditRequests($transport);
        $this->exports = new AuditExports($transport);
    }
}
