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
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\YesNoDefault;
use Core\MonitoringServer\Model\MonitoringServer;

class NewServiceTemplate
{
    public const MAX_NAME_LENGTH = 255,
                 MAX_ALIAS_LENGTH = 255,
                 MAX_COMMENT_LENGTH = 65535,
                 MAX_NOTES_LENGTH = 65535,
                 MAX_NOTES_URL_LENGTH = 65535,
                 MAX_ACTION_URL_LENGTH = 65535,
                 MAX_ICON_ALT_LENGTH = 200;

    private string $name;

    private string $alias;

    private string $className;

    /** @var string[] */
    private array $commandArguments = [];

    /** @var string[] */
    private array $eventHandlerArguments = [];

    /** @var NotificationType[] */
    private array $notificationTypes = [];

    private bool $isContactAdditiveInheritance = false;

    private bool $isContactGroupAdditiveInheritance = false;

    private bool $isActivated = true;

    private bool $isLocked = false;

    private YesNoDefault $activeChecks = YesNoDefault::Default;

    private YesNoDefault $passiveCheck = YesNoDefault::Default;

    private YesNoDefault $volatility = YesNoDefault::Default;

    private YesNoDefault $checkFreshness = YesNoDefault::Default;

    private YesNoDefault $eventHandlerEnabled = YesNoDefault::Default;

    private YesNoDefault $flapDetectionEnabled = YesNoDefault::Default;

    private YesNoDefault $notificationsEnabled = YesNoDefault::Default;

    private ?string $comment;

    private ?string $note;

    private ?string $noteUrl;

    private ?string $actionUrl;

    private ?string $iconAlternativeText;

    private ?int $graphTemplateId;

    private ?int $serviceTemplateParentId;

    private ?int $commandId;

    private ?int $eventHandlerId;

    private ?int $notificationTimePeriodId;

    private ?int $checkTimePeriodId;

    private ?int $iconId;

    private ?int $severityId;

    private ?int $maxCheckAttempts;

    private ?int $normalCheckInterval;

    private ?int $retryCheckInterval;

    private ?int $freshnessThreshold;

    private ?int $lowFlapThreshold;

    private ?int $highFlapThreshold;

    private ?int $notificationInterval;

    private ?int $recoveryNotificationDelay;

    private ?int $firstNotificationDelay;

    private ?int $acknowledgementTimeout;

    /**
     * @param string $name
     * @param string $alias
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        string $name,
        string $alias,
    ) {
        $this->className = (new \ReflectionClass($this))->getShortName();
        $this->setName($name);
        $this->setAlias($alias);
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $name = trim($name);
        Assertion::notEmptyString($name, $this->className . '::name');
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, $this->className . '::name');
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $alias
     *
     * @throws AssertionFailedException
     */
    public function setAlias(string $alias): void
    {
        $alias = preg_replace('/\s{2,}/', ' ', $alias);
        if ($alias === null) {
            throw AssertionException::notNull($this->className . '::alias');
        }
        $alias = trim($alias);
        Assertion::notEmptyString($alias, $this->className . '::alias');
        Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, $this->className . '::alias');
        Assertion::unauthorisedCharacters(
            $alias,
            MonitoringServer::ILLEGAL_CHARACTERS,
            $this->className . '::alias'
        );
        $this->alias = $alias;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @param string $commandArgument
     */
    public function addCommandArgument(string $commandArgument): void
    {
        $this->commandArguments[] = $commandArgument;
    }

    /**
     * @return list<string>
     */
    public function getCommandArguments(): array
    {
        return $this->commandArguments;
    }

    /**
     * @param string $eventHandlerArgument
     */
    public function addEventHandlerArgument(string $eventHandlerArgument): void
    {
        $this->eventHandlerArguments[] = $eventHandlerArgument;
    }

    /**
     * @return list<string>
     */
    public function getEventHandlerArguments(): array
    {
        return $this->eventHandlerArguments;
    }

    /**
     * @param NotificationType $notificationType
     */
    public function addNotificationType(NotificationType $notificationType): void
    {
        $this->notificationTypes[] = $notificationType;
    }

    /**
     * @return NotificationType[]
     */
    public function getNotificationTypes(): array
    {
        return $this->notificationTypes;
    }

    /**
     * @param bool $isContactAdditiveInheritance
     */
    public function setContactAdditiveInheritance(bool $isContactAdditiveInheritance): void
    {
        $this->isContactAdditiveInheritance = $isContactAdditiveInheritance;
    }

    /**
     * @return bool
     */
    public function isContactAdditiveInheritance(): bool
    {
        return $this->isContactAdditiveInheritance;
    }

    /**
     * @param bool $isContactGroupAdditiveInheritance
     */
    public function setContactGroupAdditiveInheritance(bool $isContactGroupAdditiveInheritance): void
    {
        $this->isContactGroupAdditiveInheritance = $isContactGroupAdditiveInheritance;
    }

    /**
     * @return bool
     */
    public function isContactGroupAdditiveInheritance(): bool
    {
        return $this->isContactGroupAdditiveInheritance;
    }

    /**
     * @param bool $isActivated
     */
    public function setActivated(bool $isActivated): void
    {
        $this->isActivated = $isActivated;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @param bool $isLocked
     */
    public function setLocked(bool $isLocked): void
    {
        $this->isLocked = $isLocked;
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * @param YesNoDefault $activeChecks
     */
    public function setActiveChecks(YesNoDefault $activeChecks): void
    {
        $this->activeChecks = $activeChecks;
    }

    /**
     * @return YesNoDefault
     */
    public function getActiveChecks(): YesNoDefault
    {
        return $this->activeChecks;
    }

    /**
     * @param YesNoDefault $passiveCheck
     */
    public function setPassiveCheck(YesNoDefault $passiveCheck): void
    {
        $this->passiveCheck = $passiveCheck;
    }

    /**
     * @return YesNoDefault
     */
    public function getPassiveCheck(): YesNoDefault
    {
        return $this->passiveCheck;
    }

    /**
     * @param YesNoDefault $volatility
     */
    public function setVolatility(YesNoDefault $volatility): void
    {
        $this->volatility = $volatility;
    }

    /**
     * @return YesNoDefault
     */
    public function getVolatility(): YesNoDefault
    {
        return $this->volatility;
    }

    /**
     * @param YesNoDefault $checkFreshness
     */
    public function setCheckFreshness(YesNoDefault $checkFreshness): void
    {
        $this->checkFreshness = $checkFreshness;
    }

    /**
     * @return YesNoDefault
     */
    public function getCheckFreshness(): YesNoDefault
    {
        return $this->checkFreshness;
    }

    /**
     * @param YesNoDefault $eventHandlerEnabled
     */
    public function setEventHandlerEnabled(YesNoDefault $eventHandlerEnabled): void
    {
        $this->eventHandlerEnabled = $eventHandlerEnabled;
    }

    /**
     * @return YesNoDefault
     */
    public function getEventHandlerEnabled(): YesNoDefault
    {
        return $this->eventHandlerEnabled;
    }

    /**
     * @param YesNoDefault $flapDetectionEnabled
     */
    public function setFlapDetectionEnabled(YesNoDefault $flapDetectionEnabled): void
    {
        $this->flapDetectionEnabled = $flapDetectionEnabled;
    }

    /**
     * @return YesNoDefault
     */
    public function getFlapDetectionEnabled(): YesNoDefault
    {
        return $this->flapDetectionEnabled;
    }

    /**
     * @param YesNoDefault $notificationsEnabled
     */
    public function setNotificationsEnabled(YesNoDefault $notificationsEnabled): void
    {
        $this->notificationsEnabled = $notificationsEnabled;
    }

    /**
     * @return YesNoDefault
     */
    public function getNotificationsEnabled(): YesNoDefault
    {
        return $this->notificationsEnabled;
    }

    /**
     * @param string|null $comment
     *
     * @throws AssertionFailedException
     */
    public function setComment(?string $comment): void
    {
        if ($comment !== null) {
            $comment = trim($comment);
            Assertion::notEmptyString($comment, $this->className . '::comment');
            Assertion::maxLength($comment, self::MAX_COMMENT_LENGTH, $this->className . '::comment');
        }
        $this->comment = $comment;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $note
     *
     * @throws AssertionFailedException
     */
    public function setNote(?string $note): void
    {
        if ($note !== null) {
            $note = trim($note);
            Assertion::notEmptyString($note, $this->className . '::note');
            Assertion::maxLength($note, self::MAX_NOTES_LENGTH, $this->className . '::note');
        }
        $this->note = $note;
    }

    /**
     * @return string|null
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @param string|null $noteUrl
     *
     * @throws AssertionFailedException
     */
    public function setNoteUrl(?string $noteUrl): void
    {
        if ($noteUrl !== null) {
            $noteUrl = trim($noteUrl);
            Assertion::notEmptyString($noteUrl, $this->className . '::noteUrl');
            Assertion::maxLength($noteUrl, self::MAX_NOTES_URL_LENGTH, $this->className . '::noteUrl');
        }
        $this->noteUrl = $noteUrl;
    }

    /**
     * @return string|null
     */
    public function getNoteUrl(): ?string
    {
        return $this->noteUrl;
    }

    /**
     * @param string|null $actionUrl
     *
     * @throws AssertionFailedException
     */
    public function setActionUrl(?string $actionUrl): void
    {
        if ($actionUrl !== null) {
            $actionUrl = trim($actionUrl);
            Assertion::notEmptyString($actionUrl, $this->className . '::actionUrl');
            Assertion::maxLength($actionUrl, self::MAX_ACTION_URL_LENGTH, $this->className . '::actionUrl');
        }
        $this->actionUrl = $actionUrl;
    }

    /**
     * @return string|null
     */
    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    /**
     * @param string|null $iconAlternativeText
     *
     * @throws AssertionFailedException
     */
    public function setIconAlternativeText(?string $iconAlternativeText): void
    {
        if ($iconAlternativeText !== null) {
            $iconAlternativeText = trim($iconAlternativeText);
            Assertion::notEmptyString($iconAlternativeText, $this->className . '::iconAlternativeText');
            Assertion::maxLength(
                $iconAlternativeText,
                self::MAX_ICON_ALT_LENGTH,
                $this->className . '::iconAlternativeText'
            );
        }
        $this->iconAlternativeText = $iconAlternativeText;
    }

    /**
     * @return string|null
     */
    public function getIconAlternativeText(): ?string
    {
        return $this->iconAlternativeText;
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws AssertionFailedException
     */
    public function setGraphTemplateId(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null) {
            Assertion::positiveInt($graphTemplateId, $this->className . '::graphTemplateId');
        }
        $this->graphTemplateId = $graphTemplateId;
    }

    /**
     * @return int|null
     */
    public function getGraphTemplateId(): ?int
    {
        return $this->graphTemplateId;
    }

    /**
     * @param int|null $serviceTemplateParentId
     *
     * @throws AssertionFailedException
     */
    public function setServiceTemplateParentId(?int $serviceTemplateParentId): void
    {
        if ($serviceTemplateParentId !== null) {
            Assertion::positiveInt($serviceTemplateParentId, $this->className . '::serviceTemplateParentId');
        }
        $this->serviceTemplateParentId = $serviceTemplateParentId;
    }

    /**
     * @return int|null
     */
    public function getServiceTemplateParentId(): ?int
    {
        return $this->serviceTemplateParentId;
    }

    /**
     * @param int|null $commandId
     *
     * @throws AssertionFailedException
     */
    public function setCommandId(?int $commandId): void
    {
        if ($commandId !== null) {
            Assertion::positiveInt($commandId, $this->className . '::commandId');
        }
        $this->commandId = $commandId;
    }

    /**
     * @return int|null
     */
    public function getCommandId(): ?int
    {
        return $this->commandId;
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws AssertionFailedException
     */
    public function setEventHandlerId(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null) {
            Assertion::positiveInt($eventHandlerId, $this->className . '::eventHandlerId');
        }
        $this->eventHandlerId = $eventHandlerId;
    }

    /**
     * @return int|null
     */
    public function getEventHandlerId(): ?int
    {
        return $this->eventHandlerId;
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws AssertionFailedException
     */
    public function setNotificationTimePeriodId(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null) {
            Assertion::positiveInt($notificationTimePeriodId, $this->className . '::notificationTimePeriodId');
        }
        $this->notificationTimePeriodId = $notificationTimePeriodId;
    }

    /**
     * @return int|null
     */
    public function getNotificationTimePeriodId(): ?int
    {
        return $this->notificationTimePeriodId;
    }

    /**
     * @param int|null $checkTimePeriodId
     *
     * @throws AssertionFailedException
     */
    public function setCheckTimePeriodId(?int $checkTimePeriodId): void
    {
        if ($checkTimePeriodId !== null) {
            Assertion::positiveInt($checkTimePeriodId, $this->className . '::checkTimePeriodId');
        }
        $this->checkTimePeriodId = $checkTimePeriodId;
    }

    /**
     * @return int|null
     */
    public function getCheckTimePeriodId(): ?int
    {
        return $this->checkTimePeriodId;
    }

    /**
     * @param int|null $iconId
     *
     * @throws AssertionFailedException
     */
    public function setIconId(?int $iconId): void
    {
        if ($iconId !== null) {
            Assertion::positiveInt($iconId, $this->className . '::iconId');
        }
        $this->iconId = $iconId;
    }

    /**
     * @return int|null
     */
    public function getIconId(): ?int
    {
        return $this->iconId;
    }

    /**
     * @param int|null $severityId
     *
     * @throws AssertionFailedException
     */
    public function setSeverityId(?int $severityId): void
    {
        if ($severityId !== null) {
            Assertion::positiveInt($severityId, $this->className . '::severityId');
        }
        $this->severityId = $severityId;
    }

    /**
     * @return int|null
     */
    public function getSeverityId(): ?int
    {
        return $this->severityId;
    }

    /**
     * @param int|null $maxCheckAttempts
     *
     * @throws AssertionFailedException
     */
    public function setMaxCheckAttempts(?int $maxCheckAttempts): void
    {
        if ($maxCheckAttempts !== null) {
            Assertion::min($maxCheckAttempts, 0, $this->className . '::maxCheckAttempts');
        }
        $this->maxCheckAttempts = $maxCheckAttempts;
    }

    /**
     * @return int|null
     */
    public function getMaxCheckAttempts(): ?int
    {
        return $this->maxCheckAttempts;
    }

    /**
     * @param int|null $normalCheckInterval
     *
     * @throws AssertionFailedException
     */
    public function setNormalCheckInterval(?int $normalCheckInterval): void
    {
        if ($normalCheckInterval !== null) {
            Assertion::min($normalCheckInterval, 0, $this->className . '::normalCheckInterval');
        }
        $this->normalCheckInterval = $normalCheckInterval;
    }

    /**
     * @return int|null
     */
    public function getNormalCheckInterval(): ?int
    {
        return $this->normalCheckInterval;
    }

    /**
     * @param int|null $retryCheckInterval
     *
     * @throws AssertionFailedException
     */
    public function setRetryCheckInterval(?int $retryCheckInterval): void
    {
        if ($retryCheckInterval !== null) {
            Assertion::min($retryCheckInterval, 0, $this->className . '::retryCheckInterval');
        }
        $this->retryCheckInterval = $retryCheckInterval;
    }

    /**
     * @return int|null
     */
    public function getRetryCheckInterval(): ?int
    {
        return $this->retryCheckInterval;
    }

    /**
     * @param int|null $freshnessThreshold
     *
     * @throws AssertionFailedException
     */
    public function setFreshnessThreshold(?int $freshnessThreshold): void
    {
        if ($freshnessThreshold !== null) {
            Assertion::min($freshnessThreshold, 0, $this->className . '::freshnessThreshold');
        }
        $this->freshnessThreshold = $freshnessThreshold;
    }

    /**
     * @return int|null
     */
    public function getFreshnessThreshold(): ?int
    {
        return $this->freshnessThreshold;
    }

    /**
     * @param int|null $lowFlapThreshold
     *
     * @throws AssertionFailedException
     */
    public function setLowFlapThreshold(?int $lowFlapThreshold): void
    {
        if ($lowFlapThreshold !== null) {
            Assertion::min($lowFlapThreshold, 0, $this->className . '::lowFlapThreshold');
        }
        $this->lowFlapThreshold = $lowFlapThreshold;
    }

    /**
     * @return int|null
     */
    public function getLowFlapThreshold(): ?int
    {
        return $this->lowFlapThreshold;
    }

    /**
     * @param int|null $highFlapThreshold
     *
     * @throws AssertionFailedException
     */
    public function setHighFlapThreshold(?int $highFlapThreshold): void
    {
        if ($highFlapThreshold !== null) {
            Assertion::min($highFlapThreshold, 0, $this->className . '::highFlapThreshold');
        }
        $this->highFlapThreshold = $highFlapThreshold;
    }

    /**
     * @return int|null
     */
    public function getHighFlapThreshold(): ?int
    {
        return $this->highFlapThreshold;
    }

    /**
     * @param int|null $notificationInterval
     *
     * @throws AssertionFailedException
     */
    public function setNotificationInterval(?int $notificationInterval): void
    {
        if ($notificationInterval !== null) {
            Assertion::min($notificationInterval, 0, $this->className . '::notificationInterval');
        }
        $this->notificationInterval = $notificationInterval;
    }

    /**
     * @return int|null
     */
    public function getNotificationInterval(): ?int
    {
        return $this->notificationInterval;
    }

    /**
     * @param int|null $recoveryNotificationDelay
     *
     * @throws AssertionFailedException
     */
    public function setRecoveryNotificationDelay(?int $recoveryNotificationDelay): void
    {
        if ($recoveryNotificationDelay !== null) {
            Assertion::min($recoveryNotificationDelay, 0, $this->className . '::recoveryNotificationDelay');
        }
        $this->recoveryNotificationDelay = $recoveryNotificationDelay;
    }

    /**
     * @return int|null
     */
    public function getRecoveryNotificationDelay(): ?int
    {
        return $this->recoveryNotificationDelay;
    }

    /**
     * @param int|null $firstNotificationDelay
     *
     * @throws AssertionFailedException
     */
    public function setFirstNotificationDelay(?int $firstNotificationDelay): void
    {
        if ($firstNotificationDelay !== null) {
            Assertion::min($firstNotificationDelay, 0, $this->className . '::firstNotificationDelay');
        }
        $this->firstNotificationDelay = $firstNotificationDelay;
    }

    /**
     * @return int|null
     */
    public function getFirstNotificationDelay(): ?int
    {
        return $this->firstNotificationDelay;
    }

    /**
     * @param int|null $acknowledgementTimeout
     *
     * @throws AssertionFailedException
     */
    public function setAcknowledgementTimeout(?int $acknowledgementTimeout): void
    {
        if ($acknowledgementTimeout !== null) {
            Assertion::min($acknowledgementTimeout, 0, $this->className . '::acknowledgementTimeout');
        }
        $this->acknowledgementTimeout = $acknowledgementTimeout;
    }

    /**
     * @return int|null
     */
    public function getAcknowledgementTimeout(): ?int
    {
        return $this->acknowledgementTimeout;
    }
}
