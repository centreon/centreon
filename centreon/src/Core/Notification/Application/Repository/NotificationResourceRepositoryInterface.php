<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Notification\Application\Repository;

use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\Repository\ReadNotificationResourceRepositoryInterface as ReadRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationResourceRepositoryInterface as WriteRepositoryInterface;
use Core\Notification\Domain\Model\HostEvent;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Domain\Model\ServiceEvent;

interface NotificationResourceRepositoryInterface extends ReadRepositoryInterface, WriteRepositoryInterface
{
    /**
     * Indicate whether the repository is valid for the provided resource type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function supportResourceType(string $type): bool;

    /**
     * Get associated Event enum class.
     *
     * @return class-string<HostEvent|ServiceEvent>
     */
    public function eventEnum(): string;

    /**
     * Get associated Event enum converter class.
     *
     * @return class-string<NotificationHostEventConverter|NotificationServiceEventConverter>
     */
    public function eventEnumConverter(): string;

    /**
     * Get associated resource type.
     *
     * @return NotificationResource::TYPE_*
     */
    public function resourceType(): string;
}
