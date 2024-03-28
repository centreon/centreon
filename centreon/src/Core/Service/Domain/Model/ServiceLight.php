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

declare(strict_types=1);

namespace Core\Service\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;

class ServiceLight
{
    public const MAX_NAME_LENGTH = NewService::MAX_NAME_LENGTH;

    /**
     * @param int $id
     * @param TrimmedString $name
     * @param int[] $hostIds
     * @param int[] $categoryIds
     * @param ServiceGroupRelation[] $groups
     * @param null|SimpleEntity $serviceTemplate
     * @param null|SimpleEntity $notificationTimePeriod
     * @param null|SimpleEntity $checkTimePeriod
     * @param null|SimpleEntity $severity
     * @param null|int $normalCheckInterval
     * @param null|int $retryCheckInterval
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly TrimmedString $name,
        private readonly array $hostIds,
        private readonly array $categoryIds = [],
        private readonly array $groups = [],
        private readonly ?SimpleEntity $serviceTemplate = null,
        private readonly ?SimpleEntity $notificationTimePeriod = null,
        private readonly ?SimpleEntity $checkTimePeriod = null,
        private readonly ?SimpleEntity $severity = null,
        private readonly ?int $normalCheckInterval = null,
        private readonly ?int $retryCheckInterval = null,
        private readonly bool $isActivated = true,
    ) {
        $className = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($id, "{$className}::id");

        Assertion::notEmptyString($this->name->value, "{$className}::name");
        Assertion::maxLength($this->name->value, self::MAX_NAME_LENGTH, "{$className}::name");

        Assertion::notEmpty($hostIds, "{$className}::hostIds");
        Assertion::arrayOfTypeOrNull('int', $hostIds, "{$className}::hostIds");
        Assertion::arrayOfTypeOrNull('int', $hostIds, "{$className}::categoryIds");

        if ($normalCheckInterval !== null) {
            Assertion::min($normalCheckInterval, 0, "{$className}::normalCheckInterval");
        }
        if ($retryCheckInterval !== null) {
            Assertion::min($retryCheckInterval, 0, "{$className}::retryCheckInterval");
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name->value;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @return null|SimpleEntity
     */
    public function getServiceTemplate(): ?SimpleEntity
    {
        return $this->serviceTemplate;
    }

    /**
     * @return null|SimpleEntity
     */
    public function getNotificationTimePeriod(): ?SimpleEntity
    {
        return $this->notificationTimePeriod;
    }

    /**
     * @return null|SimpleEntity
     */
    public function getCheckTimePeriod(): ?SimpleEntity
    {
        return $this->checkTimePeriod;
    }

    /**
     * @return null|SimpleEntity
     */
    public function getSeverity(): ?SimpleEntity
    {
        return $this->severity;
    }

    /**
     * @return null|int
     */
    public function getNormalCheckInterval(): ?int
    {
        return $this->normalCheckInterval;
    }

    /**
     * @return null|int
     */
    public function getRetryCheckInterval(): ?int
    {
        return $this->retryCheckInterval;
    }

    /**
     * @return int[]
     */
    public function getHostIds(): array
    {
        return $this->hostIds;
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /**
     * @return ServiceGroupRelation[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
