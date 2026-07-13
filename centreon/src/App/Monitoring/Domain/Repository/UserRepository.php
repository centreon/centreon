<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Repository;

use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\User\User;
use App\Monitoring\Domain\Aggregate\User\UserId;
use App\Monitoring\Domain\Exception\UserNotFoundException;

interface UserRepository {
    /**
     * @throws UserNotFoundException
     */
    public function get(UserId $id): User;

    /**
     * @param NotificationId $id
     *
     * @return list<User>
     */
    public function findByNotification(NotificationId $id): array;
}
