<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\ServiceGroup\ServiceGroup;

interface ServiceGroupRepository {
    /**
     * @return list<ServiceGroup>
     */
    public function findByNotificationId(NotificationId $id): array;
}
