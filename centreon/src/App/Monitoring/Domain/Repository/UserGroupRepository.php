<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroup;

interface UserGroupRepository {
    /**
     * @return list<UserGroup>
     */
    public function findByNotification(NotificationId $id): array;
}
