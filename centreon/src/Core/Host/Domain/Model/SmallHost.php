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

declare(strict_types = 1);

namespace Core\Host\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;

class SmallHost
{
    public const MAX_NAME_LENGTH = NewHost::MAX_NAME_LENGTH,
        MAX_ADDRESS_LENGTH = NewHost::MAX_ADDRESS_LENGTH;

    /** @var list<int> */
    private array $categoryIds = [];

    /** @var list<int> */
    private array $groupIds = [];

    /** @var list<int> */
    private array $templateIds = [];

    /**
     * @param int $id
     * @param TrimmedString $name
     * @param ?TrimmedString $alias
     * @param TrimmedString $ipAddress
     * @param int|null $normalCheckInterval
     * @param int|null $retryCheckInterval
     * @param bool $isActivated
     * @param SimpleEntity $monitoringServer
     * @param SimpleEntity|null $checkTimePeriod
     * @param SimpleEntity|null $notificationTimePeriod
     * @param SimpleEntity|null $severity
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly TrimmedString $name,
        private readonly ?TrimmedString $alias,
        private readonly TrimmedString $ipAddress,
        private readonly ?int $normalCheckInterval,
        private readonly ?int $retryCheckInterval,
        private readonly bool $isActivated,
        private readonly SimpleEntity $monitoringServer,
        private readonly ?SimpleEntity $checkTimePeriod,
        private readonly ?SimpleEntity $notificationTimePeriod,
        private readonly ?SimpleEntity $severity,
    )
    {
        $shortName = 'Host';
        Assertion::positiveInt($id, "{$shortName}::id");
        Assertion::notEmptyString($name->value, "{$shortName}::name");
        Assertion::notEmptyString($ipAddress->value, "{$shortName}::ipAddress");

        if ($normalCheckInterval !== null) {
            Assertion::positiveInt($normalCheckInterval, "{$shortName}::checkInterval");
        }
        if ($retryCheckInterval !== null) {
            Assertion::min($retryCheckInterval, 1, "{$shortName}::retryCheckInterval");
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): TrimmedString
    {
        return $this->name;
    }

    public function getAlias(): ?TrimmedString
    {
        return $this->alias;
    }

    public function getIpAddress(): TrimmedString
    {
        return $this->ipAddress;
    }

    public function getNormalCheckInterval(): ?int
    {
        return $this->normalCheckInterval;
    }

    public function getRetryCheckInterval(): ?int
    {
        return $this->retryCheckInterval;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function getCheckTimePeriod(): ?SimpleEntity
    {
        return $this->checkTimePeriod;
    }

    public function getNotificationTimePeriod(): ?SimpleEntity
    {
        return $this->notificationTimePeriod;
    }

    public function getSeverity(): ?SimpleEntity
    {
        return $this->severity;
    }

    public function getMonitoringServer(): SimpleEntity
    {
        return $this->monitoringServer;
    }

    public function addCategoryId(int $categoryId): void
    {
        $this->categoryIds[] = $categoryId;
    }

    /**
     * @return list<int>
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    public function addGroupId(int $groupId): void
    {
        $this->groupIds[] = $groupId;
    }

    /**
     * @return list<int>
     */
    public function getGroupIds(): array
    {
        return $this->groupIds;
    }

    public function addTemplateId(int $templateId): void
    {
        $this->templateIds[] = $templateId;
    }

    /**
     * @return list<int>
     */
    public function getTemplateIds(): array
    {
        return $this->templateIds;
    }
}
