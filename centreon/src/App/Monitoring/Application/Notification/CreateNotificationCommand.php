<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Notification;

use App\Monitoring\Domain\Aggregate\Notification\NotificationName;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;

final readonly class CreateNotificationCommand {
    public function __construct(
        public NotificationName $name,
        public bool $isActivated,
        public TimePeriodId $timePeriodId,
        public int $creatorId,
    )
    {
    }
}
