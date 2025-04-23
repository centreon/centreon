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

namespace Core\HostTemplate\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\YesNoDefault;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;

class NewHostTemplate
{
    public const MAX_NAME_LENGTH = 200;
    public const MAX_ALIAS_LENGTH = 200;
    public const MAX_SNMP_COMMUNITY_LENGTH = 255;
    public const MAX_NOTE_URL_LENGTH = 65535;
    public const MAX_NOTE_LENGTH = 65535;
    public const MAX_ACTION_URL_LENGTH = 65535;
    public const MAX_ICON_ALT_LENGTH = 200;
    public const MAX_COMMENT_LENGTH = 65535;

    /**
     * @param string $name
     * @param string $alias
     * @param null|SnmpVersion $snmpVersion
     * @param string $snmpCommunity
     * @param null|int $timezoneId
     * @param null|int $severityId
     * @param null|int $checkCommandId
     * @param string[] $checkCommandArgs
     * @param null|int $checkTimeperiodId
     * @param null|int $maxCheckAttempts
     * @param null|int $normalCheckInterval
     * @param null|int $retryCheckInterval
     * @param YesNoDefault $activeCheckEnabled
     * @param YesNoDefault $passiveCheckEnabled
     * @param YesNoDefault $notificationEnabled
     * @param HostEvent[] $notificationOptions
     * @param null|int $notificationInterval
     * @param null|int $notificationTimeperiodId
     * @param bool $addInheritedContactGroup
     * @param bool $addInheritedContact
     * @param null|int $firstNotificationDelay
     * @param null|int $recoveryNotificationDelay
     * @param null|int $acknowledgementTimeout
     * @param YesNoDefault $freshnessChecked
     * @param null|int $freshnessThreshold
     * @param YesNoDefault $flapDetectionEnabled
     * @param null|int $lowFlapThreshold
     * @param null|int $highFlapThreshold
     * @param YesNoDefault $eventHandlerEnabled
     * @param null|int $eventHandlerCommandId
     * @param string[] $eventHandlerCommandArgs
     * @param string $noteUrl
     * @param string $note
     * @param string $actionUrl
     * @param null|int $iconId
     * @param string $iconAlternative
     * @param string $comment
     * @param bool $isLocked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected string $alias,
        protected ?SnmpVersion $snmpVersion = null,
        protected string $snmpCommunity = '',
        protected ?int $timezoneId = null,
        protected ?int $severityId = null,
        protected ?int $checkCommandId = null,
        protected array $checkCommandArgs = [],
        protected ?int $checkTimeperiodId = null,
        protected ?int $maxCheckAttempts = null,
        protected ?int $normalCheckInterval = null,
        protected ?int $retryCheckInterval = null,
        protected YesNoDefault $activeCheckEnabled = YesNoDefault::Default,
        protected YesNoDefault $passiveCheckEnabled = YesNoDefault::Default,
        protected YesNoDefault $notificationEnabled = YesNoDefault::Default,
        protected array $notificationOptions = [],
        protected ?int $notificationInterval = null,
        protected ?int $notificationTimeperiodId = null,
        protected bool $addInheritedContactGroup = false,
        protected bool $addInheritedContact = false,
        protected ?int $firstNotificationDelay = null,
        protected ?int $recoveryNotificationDelay = null,
        protected ?int $acknowledgementTimeout = null,
        protected YesNoDefault $freshnessChecked = YesNoDefault::Default,
        protected ?int $freshnessThreshold = null,
        protected YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        protected ?int $lowFlapThreshold = null,
        protected ?int $highFlapThreshold = null,
        protected YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        protected ?int $eventHandlerCommandId = null,
        protected array $eventHandlerCommandArgs = [],
        protected string $noteUrl = '',
        protected string $note = '',
        protected string $actionUrl = '',
        protected ?int $iconId = null,
        protected string $iconAlternative = '',
        protected string $comment = '',
        protected readonly bool $isLocked = false
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        // Formating and assertions on string properties
        $this->name = self::formatName($name);
        $this->checkCommandArgs = array_map(trim(...), $checkCommandArgs);
        $this->eventHandlerCommandArgs = array_map(trim(...), $eventHandlerCommandArgs);
        $this->alias = trim($alias);
        $this->snmpCommunity = trim($snmpCommunity);
        $this->note = trim($note);
        $this->noteUrl = trim($noteUrl);
        $this->actionUrl = trim($actionUrl);
        $this->iconAlternative = trim($iconAlternative);
        $this->comment = trim($comment);

        Assertion::notEmptyString($this->name, "{$shortName}::name");
        Assertion::notEmptyString($this->alias, "{$shortName}::alias");

        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");
        Assertion::maxLength($this->alias, self::MAX_ALIAS_LENGTH, "{$shortName}::alias");
        Assertion::maxLength($this->snmpCommunity, self::MAX_SNMP_COMMUNITY_LENGTH, "{$shortName}::snmpCommunity");

        Assertion::maxLength($this->noteUrl, self::MAX_NOTE_URL_LENGTH, "{$shortName}::noteUrl");
        Assertion::maxLength($this->note, self::MAX_NOTE_LENGTH, "{$shortName}::note");
        Assertion::maxLength($this->actionUrl, self::MAX_ACTION_URL_LENGTH, "{$shortName}::actionUrl");
        Assertion::maxLength($this->iconAlternative, self::MAX_ICON_ALT_LENGTH, "{$shortName}::iconAlternative");
        Assertion::maxLength($this->comment, self::MAX_COMMENT_LENGTH, "{$shortName}::comment");

        // Assertions on ForeignKeys
        $foreignKeys = [
            'timezoneId' => $timezoneId,
            'severityId' => $severityId,
            'checkCommandId' => $checkCommandId,
            'checkTimeperiodId' => $checkTimeperiodId,
            'notificationTimeperiodId' => $notificationTimeperiodId,
            'eventHandlerCommandId' => $eventHandlerCommandId,
            'iconId' => $iconId,
        ];
        foreach ($foreignKeys as $foreignKeyName => $foreignKeyValue) {
            if (null !== $foreignKeyValue) {
                Assertion::positiveInt($foreignKeyValue, "{$shortName}::{$foreignKeyName}");
            }
        }

        // Assertion on integer properties
        Assertion::min($maxCheckAttempts ?? 0, 0, "{$shortName}::maxCheckAttempts");
        Assertion::min($normalCheckInterval ?? 0, 0, "{$shortName}::normalCheckInterval");
        Assertion::min($retryCheckInterval ?? 0, 0, "{$shortName}::retryCheckInterval");
        Assertion::min($notificationInterval ?? 0, 0, "{$shortName}::notificationInterval");
        Assertion::min($firstNotificationDelay ?? 0, 0, "{$shortName}::firstNotificationDelay");
        Assertion::min($recoveryNotificationDelay ?? 0, 0, "{$shortName}::recoveryNotificationDelay");
        Assertion::min($acknowledgementTimeout ?? 0, 0, "{$shortName}::acknowledgementTimeout");
        Assertion::min($freshnessThreshold ?? 0, 0, "{$shortName}::freshnessThreshold");
        Assertion::min($lowFlapThreshold ?? 0, 0, "{$shortName}::lowFlapThreshold");
        Assertion::min($highFlapThreshold ?? 0, 0, "{$shortName}::highFlapThreshold");
    }

    /**
     * Format a string as per domain rules for a host template name.
     *
     * @param string $name
     */
    final public static function formatName(string $name): string
    {
        return str_replace(' ', '_', trim($name));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getSnmpCommunity(): string
    {
        return $this->snmpCommunity;
    }

    /**
     * @return string[]
     */
    public function getCheckCommandArgs(): array
    {
        return $this->checkCommandArgs;
    }

    /**
     * @return string[]
     */
    public function getEventHandlerCommandArgs(): array
    {
        return $this->eventHandlerCommandArgs;
    }

    public function getNoteUrl(): string
    {
        return $this->noteUrl;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getActionUrl(): string
    {
        return $this->actionUrl;
    }

    public function getIconAlternative(): string
    {
        return $this->iconAlternative;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getTimezoneId(): ?int
    {
        return $this->timezoneId;
    }

    public function getSeverityId(): ?int
    {
        return $this->severityId;
    }

    public function getCheckCommandId(): ?int
    {
        return $this->checkCommandId;
    }

    public function getCheckTimeperiodId(): ?int
    {
        return $this->checkTimeperiodId;
    }

    public function getNotificationTimeperiodId(): ?int
    {
        return $this->notificationTimeperiodId;
    }

    public function getEventHandlerCommandId(): ?int
    {
        return $this->eventHandlerCommandId;
    }

    public function getIconId(): ?int
    {
        return $this->iconId;
    }

    public function getMaxCheckAttempts(): ?int
    {
        return $this->maxCheckAttempts;
    }

    public function getNormalCheckInterval(): ?int
    {
        return $this->normalCheckInterval;
    }

    public function getRetryCheckInterval(): ?int
    {
        return $this->retryCheckInterval;
    }

    public function getNotificationInterval(): ?int
    {
        return $this->notificationInterval;
    }

    public function getFirstNotificationDelay(): ?int
    {
        return $this->firstNotificationDelay;
    }

    public function getRecoveryNotificationDelay(): ?int
    {
        return $this->recoveryNotificationDelay;
    }

    public function getAcknowledgementTimeout(): ?int
    {
        return $this->acknowledgementTimeout;
    }

    public function getFreshnessThreshold(): ?int
    {
        return $this->freshnessThreshold;
    }

    public function getLowFlapThreshold(): ?int
    {
        return $this->lowFlapThreshold;
    }

    public function getHighFlapThreshold(): ?int
    {
        return $this->highFlapThreshold;
    }

    public function getSnmpVersion(): ?SnmpVersion
    {
        return $this->snmpVersion;
    }

    /**
     * @return HostEvent[]
     */
    public function getNotificationOptions(): array
    {
        return $this->notificationOptions;
    }

    public function getActiveCheckEnabled(): YesNoDefault
    {
        return $this->activeCheckEnabled;
    }

    public function getPassiveCheckEnabled(): YesNoDefault
    {
        return $this->passiveCheckEnabled;
    }

    public function getNotificationEnabled(): YesNoDefault
    {
        return $this->notificationEnabled;
    }

    public function getFreshnessChecked(): YesNoDefault
    {
        return $this->freshnessChecked;
    }

    public function getFlapDetectionEnabled(): YesNoDefault
    {
        return $this->flapDetectionEnabled;
    }

    public function getEventHandlerEnabled(): YesNoDefault
    {
        return $this->eventHandlerEnabled;
    }

    public function addInheritedContactGroup(): bool
    {
        return $this->addInheritedContactGroup;
    }

    public function addInheritedContact(): bool
    {
        return $this->addInheritedContact;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }
}
