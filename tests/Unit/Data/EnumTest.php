<?php

declare(strict_types=1);

namespace Lararoxy\Tests\Unit\Data;

use Lararoxy\Enums\TrackingStatus;
use Lararoxy\Tests\TestCase;

class EnumTest extends TestCase
{
    public function test_tracking_status_terminal_states(): void
    {
        $this->assertTrue(TrackingStatus::Processed->isTerminal());
        $this->assertTrue(TrackingStatus::Failed->isTerminal());
        $this->assertTrue(TrackingStatus::FailedToSend->isTerminal());
        $this->assertTrue(TrackingStatus::Expired->isTerminal());

        $this->assertFalse(TrackingStatus::Pending->isTerminal());
        $this->assertFalse(TrackingStatus::Sent->isTerminal());
        $this->assertFalse(TrackingStatus::CallbackReceived->isTerminal());
    }
}
