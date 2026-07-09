<?php

declare(strict_types=1);

namespace App\Monitoring\Application\Notification;

use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Event\NotificationCreated;
use App\Monitoring\Domain\Repository\NotificationRepository;
use App\Monitoring\Domain\Repository\TimePeriodRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreateNotificationCommandHandler {
    public function __construct(
        private NotificationRepository $notificationRepository,
        private TimePeriodRepository $timePeriodRepository,
        private EventBus $eventBus,
    )
    {
    }

    public function __invoke(CreateNotificationCommand $command): Notification
    {
        $timePeriod = $this->timePeriodRepository->get($command->timePeriodId);
        $notification = new Notification(
            id: null,
            name: $command->name,
            isActivated: $command->isActivated,
            timePeriod: $timePeriod
        );
        $this->notificationRepository->add($notification);
        $this->eventBus->fire(new NotificationCreated($notification, $command->creatorId));

        return $notification;
    }
}
