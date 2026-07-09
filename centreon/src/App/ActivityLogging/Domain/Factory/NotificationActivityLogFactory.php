<?php

declare(strict_types=1);

namespace App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\Target;
use App\ActivityLogging\Domain\Aggregate\TargetId;
use App\ActivityLogging\Domain\Aggregate\TargetName;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Shared\Domain\Aggregate\AggregateRoot;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements ActivityLogFactoryInterface<Notification>
 */
#[AsTaggedItem(index: Notification::class)]
class NotificationActivityLogFactory implements ActivityLogFactoryInterface {
    public function create(ActionEnum $action, AggregateRoot $aggregate, Actor $firedBy, \DateTimeImmutable $firedAt): ActivityLog
    {
        $target = new Target(
            id: new TargetId($aggregate->id()->value),
            name: new TargetName($aggregate->name->value),
            type: TargetTypeEnum::Notification,
        );

        return new ActivityLog(
            id: null,
            action: $action,
            actor: $firedBy,
            target: $target,
            performedAt: $firedAt,
        );
    }
}
