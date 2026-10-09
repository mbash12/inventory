<?php

namespace Src\Services;

use RuntimeException;

/**
 * Accounting refused to apply a change on business grounds (qty below what was
 * delivered, closed order, closed period, ...). Retrying will not help until
 * somebody resolves it, so callers mark the PO as blocked instead of failed.
 */
class AccountingSyncBlocked extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $reasons
     */
    public function __construct(
        public readonly int $httpStatus,
        string $message,
        public readonly array $reasons = [],
    ) {
        parent::__construct($message, $httpStatus);
    }
}
