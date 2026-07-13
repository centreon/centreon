<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;

interface HostGroupRepository {

    /**
     * @return list<HostGroup>
     */
    public function findByNotificationId(NotificationId $id): array;
}
