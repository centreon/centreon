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

namespace Core\Host\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Common\Domain\YesNoDefault;
use Core\Domain\Common\GeoCoords;

class NewHost
{
    public const MAX_NAME_LENGTH = 200;
    public const MAX_ALIAS_LENGTH = 200;
    public const MAX_SNMP_COMMUNITY_LENGTH = 255;
    public const MAX_ADDRESS_LENGTH = 255;
    public const MAX_NOTE_URL_LENGTH = 65535;
    public const MAX_NOTE_LENGTH = 65535;
    public const MAX_ACTION_URL_LENGTH = 65535;
    public const MAX_ICON_ALT_LENGTH = 200;
    public const MAX_COMMENT_LENGTH = 65535;

    /**
     * @param int $monitoringServerId
     * @param string $name
     * @param string $address
     * @param null|GeoCoords $geoCoordinates
     * @param null|SnmpVersion $snmpVersion
     * @param string $alias
     * @param string $snmpCommunity
     * @param string $noteUrl
     * @param string $note
     * @param string $actionUrl
     * @param string $iconAlternative
     * @param string $comment
     * @param string[] $checkCommandArgs
     * @param string[] $eventHandlerCommandArgs
     * @param HostEvent[] $notificationOptions
     * @param null|int $timezoneId
     * @param null|int $severityId
     * @param null|int $checkCommandId
     * @param null|int $checkTimeperiodId
     * @param null|int $maxCheckAttempts
     * @param null|int $normalCheckInterval
     * @param null|int $retryCheckInterval
     * @param null|int $notificationInterval
     * @param null|int $notificationTimeperiodId
     * @param null|int $eventHandlerCommandId
     * @param null|int $iconId
     * @param null|int $firstNotificationDelay
     * @param null|int $recoveryNotificationDelay
     * @param null|int $acknowledgementTimeout
     * @param null|int $freshnessThreshold
     * @param null|int $lowFlapThreshold
     * @param null|int $highFlapThreshold
     * @param YesNoDefault $activeCheckEnabled
     * @param YesNoDefault $passiveCheckEnabled
     * @param YesNoDefault $notificationEnabled
     * @param YesNoDefault $freshnessChecked
     * @param YesNoDefault $flapDetectionEnabled
     * @param YesNoDefault $eventHandlerEnabled
     * @param bool $addInheritedContactGroup
     * @param bool $addInheritedContact
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected int $monitoringServerId,
        protected string $name,
        protected string $address,
        protected ?SnmpVersion $snmpVersion = null,
        protected ?GeoCoords $geoCoordinates = null,
        protected string $alias = '',
        protected string $snmpCommunity = '',
        protected string $noteUrl = '',
        protected string $note = '',
        protected string $actionUrl = '',
        protected string $iconAlternative = '',
        protected string $comment = '',
        protected array $checkCommandArgs = [],
        protected array $eventHandlerCommandArgs = [],
        protected array $notificationOptions = [],
        protected ?int $timezoneId = null,
        protected ?int $severityId = null,
        protected ?int $checkCommandId = null,
        protected ?int $checkTimeperiodId = null,
        protected ?int $notificationTimeperiodId = null,
        protected ?int $eventHandlerCommandId = null,
        protected ?int $iconId = null,
        protected ?int $maxCheckAttempts = null,
        protected ?int $normalCheckInterval = null,
        protected ?int $retryCheckInterval = null,
        protected ?int $notificationInterval = null,
        protected ?int $firstNotificationDelay = null,
        protected ?int $recoveryNotificationDelay = null,
        protected ?int $acknowledgementTimeout = null,
        protected ?int $freshnessThreshold = null,
        protected ?int $lowFlapThreshold = null,
        protected ?int $highFlapThreshold = null,
        protected YesNoDefault $activeCheckEnabled = YesNoDefault::Default,
        protected YesNoDefault $passiveCheckEnabled = YesNoDefault::Default,
        protected YesNoDefault $notificationEnabled = YesNoDefault::Default,
        protected YesNoDefault $freshnessChecked = YesNoDefault::Default,
        protected YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        protected YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        protected bool $addInheritedContactGroup = false,
        protected bool $addInheritedContact = false,
        protected bool $isActivated = true,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        // Formating and assertions on string properties
        $this->name = self::formatName($name);
        $this->alias = trim($alias);
        $this->checkCommandArgs = array_map(trim(...), $checkCommandArgs);
        $this->eventHandlerCommandArgs = array_map(trim(...), $eventHandlerCommandArgs);
        $this->snmpCommunity = trim($snmpCommunity);
        $this->note = trim($note);
        $this->noteUrl = trim($noteUrl);
        $this->actionUrl = trim($actionUrl);
        $this->iconAlternative = trim($iconAlternative);
        $this->comment = trim($comment);
        $this->address = trim($this->address);

        Assertion::notEmptyString($this->name, "{$shortName}::name");

        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");
        Assertion::maxLength($this->address, self::MAX_ADDRESS_LENGTH, "{$shortName}::address");
        Assertion::maxLength($this->snmpCommunity, self::MAX_SNMP_COMMUNITY_LENGTH, "{$shortName}::snmpCommunity");
        Assertion::maxLength($this->alias, self::MAX_ALIAS_LENGTH, "{$shortName}::alias");
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
            'monitoringServerId' => $monitoringServerId,
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

        // Other assert
        Assertion::ipOrDomain($address, "{$shortName}::address");
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

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getMonitoringServerId(): int
    {
        return $this->monitoringServerId;
    }

    public function getGeoCoordinates(): GeoCoords|null
    {
        return $this->geoCoordinates;
    }
}
