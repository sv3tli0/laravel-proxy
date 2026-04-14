<?php

declare(strict_types=1);

namespace Lararoxy\Enums;

enum TrackingStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case CallbackReceived = 'callback_received';
    case Processed = 'processed';
    case Failed = 'failed';
    case FailedToSend = 'failed_to_send';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Processed, self::Failed, self::FailedToSend, self::Expired => true,
            default => false,
        };
    }
}
