<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\ActionLog\Domain\Model;

class ActionLog
{
    public const ACTION_TYPE_ADD = 'a';
    public const ACTION_TYPE_CHANGE = 'c';
    public const ACTION_TYPE_DELETE = 'd';
    public const ACTION_TYPE_ENABLE = 'enable';
    public const ACTION_TYPE_DISABLE = 'disable';

    private ?int $id;

    private \DateTime $creationDate;

    /**
     * @param string $objectType
     * @param int $objectId
     * @param string $objectName
     * @param string $actionType
     * @param int $contactId
     * @param \DateTime|null $creationDate
     */
    public function __construct(
        private readonly string $objectType,
        private readonly int $objectId,
        private readonly string $objectName,
        private readonly string $actionType,
        private readonly int $contactId,
        ?\DateTime $creationDate = null
    ) {
        if ($creationDate === null) {
            $this->creationDate = new \DateTime();
        }
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     *
     * @return ActionLog
     */
    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate(): \DateTime
    {
        return $this->creationDate;
    }

    /**
     * @return string
     */
    public function getObjectType(): string
    {
        return $this->objectType;
    }

    /**
     * @return int
     */
    public function getObjectId(): int
    {
        return $this->objectId;
    }

    /**
     * @return string
     */
    public function getObjectName(): string
    {
        return $this->objectName;
    }

    /**
     * @return string
     */
    public function getActionType(): string
    {
        return $this->actionType;
    }

    /**
     * @return int
     */
    public function getContactId(): int
    {
        return $this->contactId;
    }
}
