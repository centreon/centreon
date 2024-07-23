<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Common\Domain;

class LogAction
{
    /**
     * @param \DateTime $dataTime
     * @param string $objectType
     * @param int $objectId
     * @param string $objectName
     * @param string $actionType
     * @param int $contactId
     */
    public function __construct(
        private readonly \DateTime $dateTime,
        private readonly string $objectType,
        private readonly int $objectId,
        private readonly string $objectName,
        private readonly string $actionType,
        private readonly int $contactId,
    ) {
    }

    public function getDateTime(): \DateTime
    {
        return $this->dateTime;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    public function getObjectName(): string
    {
        return $this->objectName;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getContactId(): int
    {
        return $this->contactId;
    }
}
