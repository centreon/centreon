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

class ServiceTemplate extends NewServiceTemplate
{
    public const MAX_NAME_LENGTH = NewServiceTemplate::MAX_NAME_LENGTH;
    public const MAX_ALIAS_LENGTH = NewServiceTemplate::MAX_ALIAS_LENGTH;
    public const MAX_COMMENT_LENGTH = NewServiceTemplate::MAX_COMMENT_LENGTH;
    public const MAX_NOTES_LENGTH = NewServiceTemplate::MAX_NOTES_LENGTH;
    public const MAX_NOTES_URL_LENGTH = NewServiceTemplate::MAX_NOTES_URL_LENGTH;
    public const MAX_ACTION_URL_LENGTH = NewServiceTemplate::MAX_ACTION_URL_LENGTH;
    public const MAX_ICON_ALT_LENGTH = NewServiceTemplate::MAX_ICON_ALT_LENGTH;

    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param list<mixed> $commandArguments
     * @param list<mixed> $eventHandlerArguments
     * @param NotificationType[] $notificationTypes
     * @param list<int> $hostTemplateIds
     * @param bool $contactAdditiveInheritance
     * @param bool $contactGroupAdditiveInheritance
     * @param bool $isLocked
     * @param YesNoDefault $activeChecks
     * @param YesNoDefault $passiveCheck
     * @param YesNoDefault $volatility
     * @param YesNoDefault $checkFreshness
     * @param YesNoDefault $eventHandlerEnabled
     * @param YesNoDefault $flapDetectionEnabled
     * @param YesNoDefault $notificationsEnabled
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
        private readonly int $id,
        string $name,
        string $alias,
        array $commandArguments = [],
        array $eventHandlerArguments = [],
        array $notificationTypes = [],
        array $hostTemplateIds = [],
        bool $contactAdditiveInheritance = false,
        bool $contactGroupAdditiveInheritance = false,
        bool $isLocked = false,
        YesNoDefault $activeChecks = YesNoDefault::Default,
        YesNoDefault $passiveCheck = YesNoDefault::Default,
        YesNoDefault $volatility = YesNoDefault::Default,
        YesNoDefault $checkFreshness = YesNoDefault::Default,
        YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        YesNoDefault $notificationsEnabled = YesNoDefault::Default,
        ?string $comment = null,
        ?string $note = null,
        ?string $noteUrl = null,
        ?string $actionUrl = null,
        ?string $iconAlternativeText = null,
        ?int $graphTemplateId = null,
        ?int $serviceTemplateParentId = null,
        ?int $commandId = null,
        ?int $eventHandlerId = null,
        ?int $notificationTimePeriodId = null,
        ?int $checkTimePeriodId = null,
        ?int $iconId = null,
        ?int $severityId = null,
        ?int $maxCheckAttempts = null,
        ?int $normalCheckInterval = null,
        ?int $retryCheckInterval = null,
        ?int $freshnessThreshold = null,
        ?int $lowFlapThreshold = null,
        ?int $highFlapThreshold = null,
        ?int $notificationInterval = null,
        ?int $recoveryNotificationDelay = null,
        ?int $firstNotificationDelay = null,
        ?int $acknowledgementTimeout = null,
    ) {
        $this->className = (new \ReflectionClass($this))->getShortName();
        Assertion::positiveInt($id, "{$this->className}::id");

        parent::__construct($name, $alias);
        $this->setComment($comment);
        $this->setNote($note);
        $this->setNoteUrl($noteUrl);
        $this->setActionUrl($actionUrl);
        $this->setIconAlternativeText($iconAlternativeText);
        $this->setServiceTemplateParentId($serviceTemplateParentId);
        $this->setCommandId($commandId);
        $this->setEventHandlerId($eventHandlerId);
        $this->setNotificationTimePeriodId($notificationTimePeriodId);
        $this->setCheckTimePeriodId($checkTimePeriodId);
        $this->setIconId($iconId);
        $this->setGraphTemplateId($graphTemplateId);
        $this->setSeverityId($severityId);
        $this->setHostTemplateIds($hostTemplateIds);
        $this->setMaxCheckAttempts($maxCheckAttempts);
        $this->setNormalCheckInterval($normalCheckInterval);
        $this->setRetryCheckInterval($retryCheckInterval);
        $this->setFreshnessThreshold($freshnessThreshold);
        $this->setNotificationInterval($notificationInterval);
        $this->setRecoveryNotificationDelay($recoveryNotificationDelay);
        $this->setFirstNotificationDelay($firstNotificationDelay);
        $this->setAcknowledgementTimeout($acknowledgementTimeout);
        $this->setLowFlapThreshold($lowFlapThreshold);
        $this->setHighFlapThreshold($highFlapThreshold);
        $this->setContactAdditiveInheritance($contactAdditiveInheritance);
        $this->setContactGroupAdditiveInheritance($contactGroupAdditiveInheritance);
        $this->setLocked($isLocked);
        $this->setActiveChecks($activeChecks);
        $this->setPassiveCheck($passiveCheck);
        $this->setVolatility($volatility);
        $this->setCheckFreshness($checkFreshness);
        $this->setEventHandlerEnabled($eventHandlerEnabled);
        $this->setFlapDetectionEnabled($flapDetectionEnabled);
        $this->setNotificationsEnabled($notificationsEnabled);

        foreach (self::stringifyArguments($commandArguments) as $commandArgument) {
            $this->addCommandArgument($commandArgument);
        }

        foreach (self::stringifyArguments($eventHandlerArguments) as $eventHandlerArgument) {
            $this->addEventHandlerArgument($eventHandlerArgument);
        }

        foreach ($notificationTypes as $type) {
            Assertion::isInstanceOf($type, NotificationType::class, "{$this->className}::notificationTypes");
            $this->addNotificationType($type);
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
     * @param list<string> $eventHandlerArguments
     */
    public function setEventHandlerArguments(array $eventHandlerArguments): void
    {
        $this->resetEventHandlerArguments();
        foreach (self::stringifyArguments($eventHandlerArguments) as $eventHandlerArgument) {
            $this->addEventHandlerArgument($eventHandlerArgument);
        }
    }

    /**
     * @param list<string> $commandArguments
     */
    public function setCommandArguments(array $commandArguments): void
    {
        $this->resetCommandArguments();
        foreach (self::stringifyArguments($commandArguments) as $commandArgument) {
            $this->addCommandArgument($commandArgument);
        }
    }

    public static function formatName(string $name): ?string
    {
        $value = preg_replace('/\s{2,}/', ' ', $name);

        return ($value !== null) ? trim((string) $value) : null;
    }

    /**
     * @param list<mixed> $arguments
     *
     * @return list<string>
     */
    public static function stringifyArguments(array $arguments): array
    {
        $stringifiedArguments = [];
        foreach ($arguments as $argument) {
            if (is_scalar($argument)) {
                $stringifiedArguments[] = (string) $argument;
            }
        }

        return $stringifiedArguments;
    }
}
