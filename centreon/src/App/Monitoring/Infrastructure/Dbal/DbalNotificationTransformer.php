<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\Dbal;

use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Aggregate\Notification\NotificationName;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriod;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalNotificationRepository
 * @implements TransformerInterface<RowTypeAlias, Notification>
 */
final readonly class DbalNotificationTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<RowTypeAlias, TimePeriod> $timePeriodTransformer
     */
    public function __construct(
        #[Autowire(service: DbalTimePeriodTransformer::class)]
        private TransformerInterface $timePeriodTransformer,
    ) {
    }

    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): Notification
    {
        return new Notification(
            new NotificationId($from['notification_id']),
            new NotificationName($from['notification_name']),
            (bool) $from['notification_is_activated'],
            $this->timePeriodTransformer->transform($from),
        );
    }
}
