<?php

namespace App\Modules\Execution\Core\ValueObjects;

enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
