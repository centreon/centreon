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

namespace Core\Service\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\YesNoDefault;
use Core\Domain\Common\GeoCoords;
use Core\MonitoringServer\Model\MonitoringServer;

class Service
{
    public const MAX_NAME_LENGTH = NewService::MAX_NAME_LENGTH;
    public const MAX_COMMENT_LENGTH = NewService::MAX_COMMENT_LENGTH;
    public const MAX_NOTES_LENGTH = NewService::MAX_NOTES_LENGTH;
    public const MAX_NOTES_URL_LENGTH = NewService::MAX_NOTES_URL_LENGTH;
    public const MAX_ACTION_URL_LENGTH = NewService::MAX_ACTION_URL_LENGTH;
    public const MAX_ICON_ALT_LENGTH = NewService::MAX_ICON_ALT_LENGTH;

    /** @var list<string> */
    private array $commandArguments = [];

    /** @var list<string> */
    private array $eventHandlerArguments = [];

    /**
     * @param int $id
     * @param string $name
     * @param int $hostId
     * @param list<mixed> $commandArguments
     * @param list<mixed> $eventHandlerArguments
     * @param NotificationType[] $notificationTypes
     * @param bool $contactAdditiveInheritance
     * @param bool $contactGroupAdditiveInheritance
     * @param bool $isActivated
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
     * @param GeoCoords|null $geoCoords
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private string $name,
        private int $hostId,
        array $commandArguments = [],
        array $eventHandlerArguments = [],
        private array $notificationTypes = [],
        private bool $contactAdditiveInheritance = false,
        private bool $contactGroupAdditiveInheritance = false,
        private bool $isActivated = true,
        private YesNoDefault $activeChecks = YesNoDefault::Default,
        private YesNoDefault $passiveCheck = YesNoDefault::Default,
        private YesNoDefault $volatility = YesNoDefault::Default,
        private YesNoDefault $checkFreshness = YesNoDefault::Default,
        private YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        private YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        private YesNoDefault $notificationsEnabled = YesNoDefault::Default,
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
        private ?GeoCoords $geoCoords = null,
    ) {
        $className = (new \ReflectionClass($this))->getShortName();
        Assertion::positiveInt($id, "{$className}::id");
        Assertion::positiveInt($hostId, $className . '::hostId');

        foreach (
            [
                'name' => self::MAX_NAME_LENGTH,
                'comment' => self::MAX_COMMENT_LENGTH,
                'note' => self::MAX_NOTES_LENGTH,
                'noteUrl' => self::MAX_NOTES_URL_LENGTH,
                'actionUrl' => self::MAX_ACTION_URL_LENGTH,
                'iconAlternativeText' => self::MAX_ICON_ALT_LENGTH,
            ] as $field => $limitation
        ) {
            $propertyValue = $this->{$field};
            if (in_array($field, ['name'], true)) {
                $propertyValue = preg_replace('/\s{2,}/', ' ', $propertyValue);
                if ($propertyValue === null) {
                    throw AssertionException::notNull($className . '::' . $field);
                }
                Assertion::unauthorizedCharacters(
                    $propertyValue,
                    MonitoringServer::ILLEGAL_CHARACTERS,
                    $className . '::' . $field
                );
            }
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
            $propertyValue = $this->{$propertyName};
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
            $propertyValue = $this->{$propertyName};
            if ($propertyValue !== null) {
                Assertion::min($propertyValue, 0, "{$className}::{$propertyName}");
            }
        }
        foreach ($commandArguments as $argument) {
            if (is_scalar($argument)) {
                $this->commandArguments[] = (string) $argument;
            }
        }
        foreach ($eventHandlerArguments as $argument) {
            if (is_scalar($argument)) {
                $this->eventHandlerArguments[] = (string) $argument;
            }
        }

        foreach ($this->notificationTypes as $type) {
            Assertion::isInstanceOf($type, NotificationType::class, "{$className}::notificationTypes");
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
     * @return list<string>
     */
    public function getCommandArguments(): array
    {
        return $this->commandArguments;
    }

    /**
     * @return list<string>
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
    public function getSeverityId(): ?int
    {
        return $this->severityId;
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

    public static function formatName(string $name): string
    {
        return (string) preg_replace('/\s{2,}/', ' ', $name);
    }

    /**
     * @return int
     */
    public function getHostId(): int
    {
        return $this->hostId;
    }

    /**
     * @return GeoCoords|null
     */
    public function getGeoCoords(): ?GeoCoords
    {
        return $this->geoCoords;
    }

    public function isNameIdentical(string $testName): bool {
        return $this->name === self::formatName($testName);
    }

    /**
     * @param string $name
     *
     * @throws AssertionException
     */
    public function setName(string $name): void
    {
        $name = preg_replace('/\s{2,}/', ' ', trim($name));
        if ($name === null) {
            throw AssertionException::notNull('Service::name');
        }
        Assertion::notEmptyString($name, 'Service::name');
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, 'Service::name');
        Assertion::unauthorizedCharacters(
            $name,
            MonitoringServer::ILLEGAL_CHARACTERS,
            'Service::name'
        );

        $this->name = $name;
    }

    /**
     * @param int $hostId
     *
     * @throws AssertionException
     */
    public function setHostId(int $hostId): void
    {
        Assertion::positiveInt($hostId, 'Service::hostId');
        $this->hostId = $hostId;
    }

    public function setContactAdditiveInheritance(bool $contactAdditiveInheritance): void
    {
        $this->contactAdditiveInheritance = $contactAdditiveInheritance;
    }

    public function setContactGroupAdditiveInheritance(bool $contactGroupAdditiveInheritance): void
    {
        $this->contactGroupAdditiveInheritance = $contactGroupAdditiveInheritance;
    }

    public function setActivated(bool $isActivated): void
    {
        $this->isActivated = $isActivated;
    }

    public function setActiveChecks(YesNoDefault $activeChecks): void
    {
        $this->activeChecks = $activeChecks;
    }

    public function setPassiveCheck(YesNoDefault $passiveCheck): void
    {
        $this->passiveCheck = $passiveCheck;
    }

    public function setVolatility(YesNoDefault $volatility): void
    {
        $this->volatility = $volatility;
    }

    public function setCheckFreshness(YesNoDefault $checkFreshness): void
    {
        $this->checkFreshness = $checkFreshness;
    }

    public function setEventHandlerEnabled(YesNoDefault $eventHandlerEnabled): void
    {
        $this->eventHandlerEnabled = $eventHandlerEnabled;
    }

    public function setFlapDetectionEnabled(YesNoDefault $flapDetectionEnabled): void
    {
        $this->flapDetectionEnabled = $flapDetectionEnabled;
    }

    public function setNotificationsEnabled(YesNoDefault $notificationsEnabled): void
    {
        $this->notificationsEnabled = $notificationsEnabled;
    }

    /**
     * @param string|null $comment
     *
     * @throws AssertionException
     */
    public function setComment(?string $comment): void
    {
        if ($comment !== null) {
            Assertion::notEmptyString($comment, 'Service::comment');
            Assertion::maxLength($comment, self::MAX_COMMENT_LENGTH, 'Service::comment');
        }
        $this->comment = $comment;
    }

    /**
     * @param string|null $note
     *
     * @throws AssertionException
     */
    public function setNote(?string $note): void
    {
        if ($note !== null) {
            Assertion::notEmptyString($note, 'Service::note');
            Assertion::maxLength($note, self::MAX_NOTES_LENGTH, 'Service::note');
        }
        $this->note = $note;
    }

    /**
     * @param string|null $noteUrl
     *
     * @throws AssertionException
     */
    public function setNoteUrl(?string $noteUrl): void
    {
        if ($noteUrl !== null) {
            Assertion::notEmptyString($noteUrl, 'Service::noteUrl');
            Assertion::maxLength($noteUrl, self::MAX_NOTES_URL_LENGTH, 'Service::noteUrl');
        }
        $this->noteUrl = $noteUrl;
    }

    /**
     * @param string|null $actionUrl
     *
     * @throws AssertionException
     */
    public function setActionUrl(?string $actionUrl): void
    {
        if ($actionUrl !== null) {
            Assertion::notEmptyString($actionUrl, 'Service::actionUrl');
            Assertion::maxLength($actionUrl, self::MAX_ACTION_URL_LENGTH, 'Service::actionUrl');
        }
        $this->actionUrl = $actionUrl;
    }

    /**
     * @param string|null $iconAlternativeText
     *
     * @throws AssertionException
     */
    public function setIconAlternativeText(?string $iconAlternativeText): void
    {
        if ($iconAlternativeText !== null) {
            Assertion::notEmptyString($iconAlternativeText, 'Service::iconAlternativeText');
            Assertion::maxLength($iconAlternativeText, self::MAX_ICON_ALT_LENGTH, 'Service::iconAlternativeText');
        }
        $this->iconAlternativeText = $iconAlternativeText;
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws AssertionException
     */
    public function setGraphTemplateId(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null) {
            Assertion::positiveInt($graphTemplateId, 'Service::graphTemplateId');
        }
        $this->graphTemplateId = $graphTemplateId;
    }

    /**
     * @param int|null $serviceTemplateParentId
     *
     * @throws AssertionException
     */
    public function setServiceTemplateParentId(?int $serviceTemplateParentId): void
    {
        if ($serviceTemplateParentId !== null) {
            Assertion::positiveInt($serviceTemplateParentId, 'Service::serviceTemplateParentId');
        }
        $this->serviceTemplateParentId = $serviceTemplateParentId;
    }

    /**
     * @param int|null $commandId
     *
     * @throws AssertionException
     */
    public function setCommandId(?int $commandId): void
    {
        if ($commandId !== null) {
            Assertion::positiveInt($commandId, 'Service::commandId');
        }
        $this->commandId = $commandId;
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws AssertionException
     */
    public function setEventHandlerId(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null) {
            Assertion::positiveInt($eventHandlerId, 'Service::eventHandlerId');
        }
        $this->eventHandlerId = $eventHandlerId;
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws AssertionException
     */
    public function setNotificationTimePeriodId(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null) {
            Assertion::positiveInt($notificationTimePeriodId, 'Service::notificationTimePeriodId');
        }
        $this->notificationTimePeriodId = $notificationTimePeriodId;
    }

    /**
     * @param int|null $checkTimePeriodId
     *
     * @throws AssertionException
     */
    public function setCheckTimePeriodId(?int $checkTimePeriodId): void
    {
        if ($checkTimePeriodId !== null) {
            Assertion::positiveInt($checkTimePeriodId, 'Service::checkTimePeriodId');
        }
        $this->checkTimePeriodId = $checkTimePeriodId;
    }

    /**
     * @param int|null $iconId
     *
     * @throws AssertionException
     */
    public function setIconId(?int $iconId): void
    {
        if ($iconId !== null) {
            Assertion::positiveInt($iconId, 'Service::iconId');
        }
        $this->iconId = $iconId;
    }

    /**
     * @param int|null $severityId
     *
     * @throws AssertionException
     */
    public function setSeverityId(?int $severityId): void
    {
        if ($severityId !== null) {
            Assertion::positiveInt($severityId, 'Service::severityId');
        }
        $this->severityId = $severityId;
    }

    /**
     * @param int|null $maxCheckAttempts
     *
     * @throws AssertionException
     */
    public function setMaxCheckAttempts(?int $maxCheckAttempts): void
    {
        if ($maxCheckAttempts !== null) {
            Assertion::min($maxCheckAttempts, 0, 'Service::maxCheckAttempts');
        }
        $this->maxCheckAttempts = $maxCheckAttempts;
    }

    /**
     * @param int|null $normalCheckInterval
     *
     * @throws AssertionException
     */
    public function setNormalCheckInterval(?int $normalCheckInterval): void
    {
        if ($normalCheckInterval !== null) {
            Assertion::min($normalCheckInterval, 0, 'Service::normalCheckInterval');
        }
        $this->normalCheckInterval = $normalCheckInterval;
    }

    /**
     * @param int|null $retryCheckInterval
     *
     * @throws AssertionException
     */
    public function setRetryCheckInterval(?int $retryCheckInterval): void
    {
        if ($retryCheckInterval !== null) {
            Assertion::min($retryCheckInterval, 0, 'Service::retryCheckInterval');
        }
        $this->retryCheckInterval = $retryCheckInterval;
    }

    /**
     * @param int|null $freshnessThreshold
     *
     * @throws AssertionException
     */
    public function setFreshnessThreshold(?int $freshnessThreshold): void
    {
        if ($freshnessThreshold !== null) {
            Assertion::min($freshnessThreshold, 0, 'Service::freshnessThreshold');
        }
        $this->freshnessThreshold = $freshnessThreshold;
    }

    /**
     * @param int|null $lowFlapThreshold
     *
     * @throws AssertionException
     */
    public function setLowFlapThreshold(?int $lowFlapThreshold): void
    {
        if ($lowFlapThreshold !== null) {
            Assertion::min($lowFlapThreshold, 0, 'Service::lowFlapThreshold');
        }
        $this->lowFlapThreshold = $lowFlapThreshold;
    }

    /**
     * @param int|null $highFlapThreshold
     *
     * @throws AssertionException
     */
    public function setHighFlapThreshold(?int $highFlapThreshold): void
    {
        if ($highFlapThreshold !== null) {
            Assertion::min($highFlapThreshold, 0, 'Service::highFlapThreshold');
        }
        $this->highFlapThreshold = $highFlapThreshold;
    }

    /**
     * @param int|null $notificationInterval
     *
     * @throws AssertionException
     */
    public function setNotificationInterval(?int $notificationInterval): void
    {
        if ($notificationInterval !== null) {
            Assertion::min($notificationInterval, 0, 'Service::notificationInterval');
        }
        $this->notificationInterval = $notificationInterval;
    }

    /**
     * @param int|null $recoveryNotificationDelay
     *
     * @throws AssertionException
     */
    public function setRecoveryNotificationDelay(?int $recoveryNotificationDelay): void
    {
        if ($recoveryNotificationDelay !== null) {
            Assertion::min($recoveryNotificationDelay, 0, 'Service::recoveryNotificationDelay');
        }
        $this->recoveryNotificationDelay = $recoveryNotificationDelay;
    }

    /**
     * @param int|null $firstNotificationDelay
     *
     * @throws AssertionException
     */
    public function setFirstNotificationDelay(?int $firstNotificationDelay): void
    {
        if ($firstNotificationDelay !== null) {
            Assertion::min($firstNotificationDelay, 0, 'Service::firstNotificationDelay');
        }
        $this->firstNotificationDelay = $firstNotificationDelay;
    }

    /**
     * @param int|null $acknowledgementTimeout
     *
     * @throws AssertionException
     */
    public function setAcknowledgementTimeout(?int $acknowledgementTimeout): void
    {
        if ($acknowledgementTimeout !== null) {
            Assertion::min($acknowledgementTimeout, 0, 'Service::acknowledgementTimeout');
        }
        $this->acknowledgementTimeout = $acknowledgementTimeout;
    }

    public function setGeoCoords(?GeoCoords $geoCoords): void
    {
        $this->geoCoords = $geoCoords;
    }

    /**
     * @param string[] $commandArguments
     */
    public function setCommandArguments(array $commandArguments): void
    {
        $this->commandArguments = [];
        foreach ($commandArguments as $commandArgument) {
            if (\is_scalar($commandArgument)) {
                $this->commandArguments[] = (string) $commandArgument;
            }
        }
    }

    /**
     * @param string[] $eventHandlerArguments
     */
    public function setEventHandlerArguments(array $eventHandlerArguments): void
    {
        $this->eventHandlerArguments = [];
        foreach ($eventHandlerArguments as $eventHandlerArgument) {
            if (\is_scalar($eventHandlerArgument)) {
                $this->eventHandlerArguments[] = (string) $eventHandlerArgument;
            }
        }
    }

    /**
     * @param NotificationType[] $notificationTypes
     *
     * @throws AssertionException
     */
    public function setNotificationTypes(array $notificationTypes): void
    {
        $this->notificationTypes = [];
        foreach ($notificationTypes as $notificationType) {
            Assertion::isInstanceOf($notificationType, NotificationType::class, 'Service::notificationTypes');
            $this->notificationTypes[] = $notificationType;
        }
    }
}
