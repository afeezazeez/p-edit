<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}
