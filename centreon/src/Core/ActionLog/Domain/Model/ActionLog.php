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
    public const ACTION_TYPE_MASS_CHANGE = 'mc';
    public const ACTION_TYPE_DELETE = 'd';
    public const ACTION_TYPE_ENABLE = 'enable';
    public const ACTION_TYPE_DISABLE = 'disable';
    public const OBJECT_TYPE_COMMAND = 'command';
    public const OBJECT_TYPE_TIMEPERIOD = 'timeperiod';
    public const OBJECT_TYPE_CONTACT = 'contact';
    public const OBJECT_TYPE_CONTACTGROUP = 'contactgroup';
    public const OBJECT_TYPE_HOST = 'host';
    public const OBJECT_TYPE_HOSTGROUP = 'hostgroup';
    public const OBJECT_TYPE_SERVICE = 'service';
    public const OBJECT_TYPE_SERVICEGROUP = 'servicegroup';
    public const OBJECT_TYPE_TRAPS = 'traps';
    public const OBJECT_TYPE_ESCALATION = 'escalation';
    public const OBJECT_TYPE_HOST_DEPENDENCY = 'host dependency';
    public const OBJECT_TYPE_HOSTGROUP_DEPENDENCY = 'hostgroup dependency';
    public const OBJECT_TYPE_SERVICE_DEPENDENCY = 'service dependency';
    public const OBJECT_TYPE_SERVICEGROUP_DEPENDENCY = 'servicegroup dependency';
    public const OBJECT_TYPE_POLLER = 'poller';
    public const OBJECT_TYPE_ENGINE = 'engine';
    public const OBJECT_TYPE_BROKER = 'broker';
    public const OBJECT_TYPE_RESOURCES = 'resources';
    public const OBJECT_TYPE_META = 'meta';
    public const OBJECT_TYPE_ACCESS_GROUP = 'access group';
    public const OBJECT_TYPE_MENU_ACCESS = 'menu access';
    public const OBJECT_TYPE_RESOURCE_ACCESS = 'resource access';
    public const OBJECT_TYPE_ACTION_ACCESS = 'action access';
    public const OBJECT_TYPE_MANUFACTURER = 'manufacturer';
    public const OBJECT_TYPE_HOSTCATEGORIES = 'hostcategories';
    public const OBJECT_TYPE_SERVICECATEGORIES = 'servicecategories';
    public const AVAILABLE_OBJECT_TYPES = [
        self::OBJECT_TYPE_COMMAND,
        self::OBJECT_TYPE_TIMEPERIOD,
        self::OBJECT_TYPE_CONTACT,
        self::OBJECT_TYPE_CONTACTGROUP,
        self::OBJECT_TYPE_HOST,
        self::OBJECT_TYPE_HOSTGROUP,
        self::OBJECT_TYPE_SERVICE,
        self::OBJECT_TYPE_SERVICEGROUP,
        self::OBJECT_TYPE_TRAPS,
        self::OBJECT_TYPE_ESCALATION,
        self::OBJECT_TYPE_HOST_DEPENDENCY,
        self::OBJECT_TYPE_HOSTGROUP_DEPENDENCY,
        self::OBJECT_TYPE_SERVICE_DEPENDENCY,
        self::OBJECT_TYPE_SERVICEGROUP_DEPENDENCY,
        self::OBJECT_TYPE_POLLER,
        self::OBJECT_TYPE_ENGINE,
        self::OBJECT_TYPE_BROKER,
        self::OBJECT_TYPE_RESOURCES,
        self::OBJECT_TYPE_META,
        self::OBJECT_TYPE_ACCESS_GROUP,
        self::OBJECT_TYPE_MENU_ACCESS,
        self::OBJECT_TYPE_RESOURCE_ACCESS,
        self::OBJECT_TYPE_ACTION_ACCESS,
        self::OBJECT_TYPE_MANUFACTURER,
        self::OBJECT_TYPE_HOSTCATEGORIES,
        self::OBJECT_TYPE_SERVICECATEGORIES,
    ];

    private ?int $id = null;

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
