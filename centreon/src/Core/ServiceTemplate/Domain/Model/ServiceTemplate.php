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

namespace Core\ServiceTemplate\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\YesNoDefault;

class ServiceTemplate
{
    public const MAX_NAME_LENGTH = 255,
                 MAX_ALIAS_LENGTH = 255,
                 MAX_DESCRIPTION_LENGTH = 200,
                 MAX_COMMENT_LENGTH = 65535,
                 MAX_NOTES_LENGTH = 65535,
                 MAX_NOTES_URL_LENGTH = 65535,
                 MAX_ACTION_URL_LENGTH = 65535,
                 MAX_ICON_ALT_LENGTH = 200;

    /** @var list<string> */
    private array $commandArguments = [];

    /** @var list<string> */
    private array $eventHandlerArguments = [];

    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param list<mixed> $commandArguments
     * @param list<mixed> $eventHandlerArguments
     * @param NotificationType[] $notificationTypes
     * @param bool $contactAdditiveInheritance
     * @param bool $contactGroupAdditiveInheritance
     * @param bool $isActivated
     * @param bool $isLocked
     * @param YesNoDefault $activeChecks
     * @param YesNoDefault $passiveCheck
     * @param YesNoDefault $volatility
     * @param YesNoDefault $checkFreshness
     * @param YesNoDefault $eventHandlerEnabled
     * @param YesNoDefault $flapDetectionEnabled
     * @param YesNoDefault $notificationsEnabled
     * @param string|null $description
     * @param string|null $comment
     * @param string|null $note
     * @param string|null $noteUrl
     * @param string|null $actionUrl
     * @param string|null $iconAlternativeText
     * @param int|null $graphTemplateId
     * @param int|null $serviceTemplateParentId
     * @param int|null $commandId
     * @param int|null $eventHandlerId
     * @param int|null $notificationTimePeriodId
     * @param int|null $checkTimePeriodId
     * @param int|null $iconId
     * @param int|null $severityId
     * @param int|null $maxCheckAttempts
     * @param int|null $normalCheckInterval
     * @param int|null $retryCheckInterval
     * @param int|null $freshnessThreshold
     * @param int|null $lowFlapThreshold
     * @param int|null $highFlapThreshold
     * @param int|null $notificationInterval
     * @param int|null $recoveryNotificationDelay
     * @param int|null $firstNotificationDelay
     * @param int|null $acknowledgementTimeout
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private int $id,
        private string $name,
        private string $alias,
        array $commandArguments = [],
        array $eventHandlerArguments = [],
        private array $notificationTypes = [],
        private bool $contactAdditiveInheritance = false,
        private bool $contactGroupAdditiveInheritance = false,
        private bool $isActivated = true,
        private bool $isLocked = false,
        private YesNoDefault $activeChecks = YesNoDefault::Default,
        private YesNoDefault $passiveCheck = YesNoDefault::Default,
        private YesNoDefault $volatility = YesNoDefault::Default,
        private YesNoDefault $checkFreshness = YesNoDefault::Default,
        private YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        private YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        private YesNoDefault $notificationsEnabled = YesNoDefault::Default,
        private ?string $description = null,
        private ?string $comment = null,
        private ?string $note = null,
        private ?string $noteUrl = null,
        private ?string $actionUrl = null,
        private ?string $iconAlternativeText = null,
        private ?int $graphTemplateId = null,
        private ?int $serviceTemplateParentId = null,
        private ?int $commandId = null,
        private ?int $eventHandlerId = null,
        private ?int $notificationTimePeriodId = null,
        private ?int $checkTimePeriodId = null,
        private ?int $iconId = null,
        private ?int $severityId = null,
        private ?int $maxCheckAttempts = null,
        private ?int $normalCheckInterval = null,
        private ?int $retryCheckInterval = null,
        private ?int $freshnessThreshold = null,
        private ?int $lowFlapThreshold = null,
        private ?int $highFlapThreshold = null,
        private ?int $notificationInterval = null,
        private ?int $recoveryNotificationDelay = null,
        private ?int $firstNotificationDelay = null,
        private ?int $acknowledgementTimeout = null,
    ) {
        $className = (new \ReflectionClass($this))->getShortName();
        Assertion::positiveInt($id, "{$className}::id");
        foreach (
            [
                'name' => self::MAX_NAME_LENGTH,
                'alias' => self::MAX_ALIAS_LENGTH,
                'comment' => self::MAX_COMMENT_LENGTH,
                'description' => self::MAX_DESCRIPTION_LENGTH,
                'note' => self::MAX_NOTES_LENGTH,
                'noteUrl' => self::MAX_NOTES_URL_LENGTH,
                'actionUrl' => self::MAX_ACTION_URL_LENGTH,
                'iconAlternativeText' => self::MAX_ICON_ALT_LENGTH,
            ] as $field => $limitation
        ) {
            $propertyValue = ${$field};
            if ($propertyValue !== null) {
                $this->{$field} = trim($propertyValue);
                Assertion::notEmptyString($this->{$field}, "{$className}::{$field}");
                Assertion::maxLength($this->{$field}, $limitation, "{$className}::{$field}");
            }
        }

        // Assertions on ForeignKeys
        $foreignKeys = [
            'serviceTemplateParentId',
            'commandId',
            'eventHandlerId',
            'notificationTimePeriodId',
            'checkTimePeriodId',
            'iconId',
            'graphTemplateId',
            'severityId',
        ];
        foreach ($foreignKeys as $propertyName) {
            $propertyValue = ${$propertyName};
            if ($propertyValue !== null) {
                Assertion::positiveInt($propertyValue, "{$className}::{$propertyName}");
            }
        }
        $properties = [
            'maxCheckAttempts',
            'normalCheckInterval',
            'retryCheckInterval',
            'freshnessThreshold',
            'notificationInterval',
            'recoveryNotificationDelay',
            'firstNotificationDelay',
            'acknowledgementTimeout',
            'lowFlapThreshold',
            'highFlapThreshold',
        ];
        foreach ($properties as $propertyName) {
            $propertyValue = ${$propertyName};
            if ($propertyValue !== null) {
                Assertion::min($propertyValue, 0, "{$className}::{$propertyName}");
            }
        }

        $this->commandArguments = [];
        foreach ($commandArguments as $argument) {
            if (is_scalar($argument)) {
                $this->commandArguments[] = (string) $argument;
            }
        }

        $this->eventHandlerArguments = [];
        foreach ($eventHandlerArguments as $argument) {
            if (is_scalar($argument)) {
                $this->eventHandlerArguments[] = (string) $argument;
            }
        }

        foreach ($notificationTypes as $type) {
            if (! ($type instanceof NotificationType)) {
                Assertion::isInstanceOf($type, NotificationType::class, "{$className}::notificationTypes");
            }
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
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return string[]
     */
    public function getCommandArguments(): array
    {
        return $this->commandArguments;
    }

    /**
     * @return string[]
     */
    public function getEventHandlerArguments(): array
    {
        return $this->eventHandlerArguments;
    }

    /**
     * @return NotificationType[]
     */
    public function getNotificationTypes(): array
    {
        return $this->notificationTypes;
    }

    /**
     * @return bool
     */
    public function isContactAdditiveInheritance(): bool
    {
        return $this->contactAdditiveInheritance;
    }

    /**
     * @return bool
     */
    public function isContactGroupAdditiveInheritance(): bool
    {
        return $this->contactGroupAdditiveInheritance;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * @return YesNoDefault
     */
    public function getActiveChecks(): YesNoDefault
    {
        return $this->activeChecks;
    }

    /**
     * @return YesNoDefault
     */
    public function getPassiveCheck(): YesNoDefault
    {
        return $this->passiveCheck;
    }

    /**
     * @return YesNoDefault
     */
    public function getVolatility(): YesNoDefault
    {
        return $this->volatility;
    }

    /**
     * @return YesNoDefault
     */
    public function getCheckFreshness(): YesNoDefault
    {
        return $this->checkFreshness;
    }

    /**
     * @return YesNoDefault
     */
    public function getEventHandlerEnabled(): YesNoDefault
    {
        return $this->eventHandlerEnabled;
    }

    /**
     * @return YesNoDefault
     */
    public function getFlapDetectionEnabled(): YesNoDefault
    {
        return $this->flapDetectionEnabled;
    }

    /**
     * @return YesNoDefault
     */
    public function getNotificationsEnabled(): YesNoDefault
    {
        return $this->notificationsEnabled;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return string|null
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @return string|null
     */
    public function getNoteUrl(): ?string
    {
        return $this->noteUrl;
    }

    /**
     * @return string|null
     */
    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    /**
     * @return string|null
     */
    public function getIconAlternativeText(): ?string
    {
        return $this->iconAlternativeText;
    }

    /**
     * @return int|null
     */
    public function getGraphTemplateId(): ?int
    {
        return $this->graphTemplateId;
    }

    /**
     * @return int|null
     */
    public function getServiceTemplateParentId(): ?int
    {
        return $this->serviceTemplateParentId;
    }

    /**
     * @return int|null
     */
    public function getCommandId(): ?int
    {
        return $this->commandId;
    }

    /**
     * @return int|null
     */
    public function getEventHandlerId(): ?int
    {
        return $this->eventHandlerId;
    }

    /**
     * @return int|null
     */
    public function getNotificationTimePeriodId(): ?int
    {
        return $this->notificationTimePeriodId;
    }

    /**
     * @return int|null
     */
    public function getCheckTimePeriodId(): ?int
    {
        return $this->checkTimePeriodId;
    }

    /**
     * @return int|null
     */
    public function getIconId(): ?int
    {
        return $this->iconId;
    }

    /**
     * @return int|null
     */
    public function getMaxCheckAttempts(): ?int
    {
        return $this->maxCheckAttempts;
    }

    /**
     * @return int|null
     */
    public function getNormalCheckInterval(): ?int
    {
        return $this->normalCheckInterval;
    }

    /**
     * @return int|null
     */
    public function getRetryCheckInterval(): ?int
    {
        return $this->retryCheckInterval;
    }

    /**
     * @return int|null
     */
    public function getFreshnessThreshold(): ?int
    {
        return $this->freshnessThreshold;
    }

    /**
     * @return int|null
     */
    public function getLowFlapThreshold(): ?int
    {
        return $this->lowFlapThreshold;
    }

    /**
     * @return int|null
     */
    public function getHighFlapThreshold(): ?int
    {
        return $this->highFlapThreshold;
    }

    /**
     * @return int|null
     */
    public function getNotificationInterval(): ?int
    {
        return $this->notificationInterval;
    }

    /**
     * @return int|null
     */
    public function getRecoveryNotificationDelay(): ?int
    {
        return $this->recoveryNotificationDelay;
    }

    /**
     * @return int|null
     */
    public function getFirstNotificationDelay(): ?int
    {
        return $this->firstNotificationDelay;
    }

    /**
     * @return int|null
     */
    public function getAcknowledgementTimeout(): ?int
    {
        return $this->acknowledgementTimeout;
    }

    /**
     * @return int|null
     */
    public function getSeverityId(): ?int
    {
        return $this->severityId;
    }
}
