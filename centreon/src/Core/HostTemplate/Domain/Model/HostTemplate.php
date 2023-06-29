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

class HostTemplate extends NewHostTemplate
{
    private string $shortName = '';

    /**
     * @param int $id
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
     * @param bool $isActivated
     * @param bool $isLocked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        string $alias,
        ?SnmpVersion $snmpVersion = null,
        string $snmpCommunity = '',
        ?int $timezoneId = null,
        ?int $severityId = null,
        ?int $checkCommandId = null,
        array $checkCommandArgs = [],
        ?int $checkTimeperiodId = null,
        ?int $maxCheckAttempts = null,
        ?int $normalCheckInterval = null,
        ?int $retryCheckInterval = null,
        YesNoDefault $activeCheckEnabled = YesNoDefault::Default,
        YesNoDefault $passiveCheckEnabled = YesNoDefault::Default,
        YesNoDefault $notificationEnabled = YesNoDefault::Default,
        array $notificationOptions = [],
        ?int $notificationInterval = null,
        ?int $notificationTimeperiodId = null,
        bool $addInheritedContactGroup = false,
        bool $addInheritedContact = false,
        ?int $firstNotificationDelay = null,
        ?int $recoveryNotificationDelay = null,
        ?int $acknowledgementTimeout = null,
        YesNoDefault $freshnessChecked = YesNoDefault::Default,
        ?int $freshnessThreshold = null,
        YesNoDefault $flapDetectionEnabled = YesNoDefault::Default,
        ?int $lowFlapThreshold = null,
        ?int $highFlapThreshold = null,
        YesNoDefault $eventHandlerEnabled = YesNoDefault::Default,
        ?int $eventHandlerCommandId = null,
        array $eventHandlerCommandArgs = [],
        string $noteUrl = '',
        string $note = '',
        string $actionUrl = '',
        ?int $iconId = null,
        string $iconAlternative = '',
        string $comment = '',
        bool $isActivated = true,
        bool $isLocked = false
    ) {
        $this->shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($id, '{$this->shortName}::id');

        parent::__construct(
            $name,
            $alias,
            $snmpVersion,
            $snmpCommunity,
            $timezoneId,
            $severityId,
            $checkCommandId,
            $checkCommandArgs,
            $checkTimeperiodId,
            $maxCheckAttempts,
            $normalCheckInterval,
            $retryCheckInterval,
            $activeCheckEnabled,
            $passiveCheckEnabled,
            $notificationEnabled,
            $notificationOptions,
            $notificationInterval,
            $notificationTimeperiodId,
            $addInheritedContactGroup,
            $addInheritedContact,
            $firstNotificationDelay,
            $recoveryNotificationDelay,
            $acknowledgementTimeout,
            $freshnessChecked,
            $freshnessThreshold,
            $flapDetectionEnabled,
            $lowFlapThreshold,
            $highFlapThreshold,
            $eventHandlerEnabled,
            $eventHandlerCommandId,
            $eventHandlerCommandArgs,
            $noteUrl,
            $note,
            $actionUrl,
            $iconId,
            $iconAlternative,
            $comment,
            $isActivated,
            $isLocked
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isNameIdentical(string $testName): bool {
        return $this->name === HostTemplate::formatName($testName);
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void {
        $this->name = $this->formatName($name);
        Assertion::notEmptyString($this->name, "{$this->shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$this->shortName}::name");
    }

    /**
     * @param string $alias
     *
     * @throws AssertionFailedException
     */
    public function setAlias(string $alias): void {
        $this->alias = trim($alias);
        Assertion::notEmptyString($this->alias, "{$this->shortName}::alias");
        Assertion::maxLength($this->alias, self::MAX_ALIAS_LENGTH, "{$this->shortName}::alias");
    }

    /**
     * @param string[] $checkCommandArgs
     */
    public function setCheckCommandArgs(array $checkCommandArgs): void{
        $this->checkCommandArgs = array_map(trim(...), $checkCommandArgs);
    }

    /**
     * @param string[] $eventHandlerCommandArgs
     */
    public function setEventHandlerCommandArgs(array $eventHandlerCommandArgs): void
    {
        $this->eventHandlerCommandArgs = array_map(trim(...), $eventHandlerCommandArgs);
    }

    /**
     * @param SnmpVersion|null $snmpVersion
     *
     * @throws AssertionFailedException
     */
    public function setSnmpVersion(SnmpVersion|null $snmpVersion): void
    {
        $this->snmpVersion = $snmpVersion;
    }

    /**
     * @param string $snmpCommunity
     *
     * @throws AssertionFailedException
     */
    public function setSnmpCommunity(string $snmpCommunity): void
    {
        $this->snmpCommunity = trim($snmpCommunity);
        Assertion::maxLength($this->snmpCommunity, self::MAX_SNMP_COMMUNITY_LENGTH, "{$this->shortName}::snmpCommunity");
    }

    /**
     * @param string $note
     *
     * @throws AssertionFailedException
     */
    public function setNote(string $note): void {
        $this->note = trim($note);
        Assertion::maxLength($this->note, self::MAX_NOTE_LENGTH, "{$this->shortName}::note");
    }

    /**
     * @param string $noteUrl
     *
     * @throws AssertionFailedException
     */
    public function setNoteUrl(string $noteUrl): void {
        $this->noteUrl = trim($noteUrl);
        Assertion::maxLength($this->noteUrl, self::MAX_NOTE_URL_LENGTH, "{$this->shortName}::noteUrl");
    }

    /**
     * @param string $actionUrl
     *
     * @throws AssertionFailedException
     */
    public function setActionUrl(string $actionUrl): void {
        $this->actionUrl = trim($actionUrl);
        Assertion::maxLength($this->actionUrl, self::MAX_ACTION_URL_LENGTH, "{$this->shortName}::actionUrl");
    }

    /**
     * @param string $iconAlternative
     *
     * @throws AssertionFailedException
     */
    public function setIconAlternative(string $iconAlternative): void {
        $this->iconAlternative = trim($iconAlternative);
        Assertion::maxLength($this->iconAlternative, self::MAX_ICON_ALT_LENGTH, "{$this->shortName}::iconAlternative");
    }

    /**
     * @param string $comment
     *
     * @throws AssertionFailedException
     */
    public function setComment(string $comment): void {
        $this->comment = trim($comment);
        Assertion::maxLength($this->comment, self::MAX_COMMENT_LENGTH, "{$this->shortName}::comment");
    }

    /**
     * @param int|null $timezoneId
     *
     * @throws AssertionFailedException
     */
    public function setTimezoneId(int|null $timezoneId): void {
        $this->timezoneId = $timezoneId;
        if ($this->timezoneId !== null) {
            Assertion::positiveInt($this->timezoneId, "{$this->shortName}::timezoneId");
        }
    }

    /**
     * @param int|null $severityId
     *
     * @throws AssertionFailedException
     */
    public function setSeverityId(int|null $severityId): void {
        $this->severityId = $severityId;
        if ($this->severityId !== null) {
            Assertion::positiveInt($this->severityId, "{$this->shortName}::severityId");
        }
    }

    /**
     * @param int|null $checkCommandId
     *
     * @throws AssertionFailedException
     */
    public function setCheckCommandId(int|null $checkCommandId): void {
        $this->checkCommandId = $checkCommandId;
        if ($this->checkCommandId !== null) {
            Assertion::positiveInt($this->checkCommandId, "{$this->shortName}::checkCommandId");
        }
    }

    /**
     * @param int|null $checkTimeperiodId
     *
     * @throws AssertionFailedException
     */
    public function setCheckTimeperiodId(int|null $checkTimeperiodId): void {
        $this->checkTimeperiodId = $checkTimeperiodId;
        if ($this->checkTimeperiodId !== null) {
            Assertion::positiveInt($this->checkTimeperiodId, "{$this->shortName}::checkTimeperiodId");
        }
    }

    /**
     * @param int|null $notificationTimeperiodId
     *
     * @throws AssertionFailedException
     */
    public function setNotificationTimeperiodId(int|null $notificationTimeperiodId): void {
        $this->notificationTimeperiodId = $notificationTimeperiodId;
        if ($this->notificationTimeperiodId !== null) {
            Assertion::positiveInt($this->notificationTimeperiodId, "{$this->shortName}::notificationTimeperiodId");
        }
    }

    /**
     * @param int|null $eventHandlerCommandId
     *
     * @throws AssertionFailedException
     */
    public function setEventHandlerCommandId(int|null $eventHandlerCommandId): void {
        $this->eventHandlerCommandId = $eventHandlerCommandId;
        if ($this->eventHandlerCommandId !== null) {
            Assertion::positiveInt($this->eventHandlerCommandId, "{$this->shortName}::eventHandlerCommandId");
        }
    }

    /**
     * @param int|null $iconId
     *
     * @throws AssertionFailedException
     */
    public function setIconId(int|null $iconId): void {
        $this->iconId = $iconId;
        if ($this->iconId !== null) {
            Assertion::positiveInt($this->iconId, "{$this->shortName}::iconId");
        }
    }

    /**
     * @param int|null $maxCheckAttempts
     *
     * @throws AssertionFailedException
     */
    public function setMaxCheckAttempts(int|null $maxCheckAttempts): void {
        $this->maxCheckAttempts = $maxCheckAttempts;
        Assertion::min($this->maxCheckAttempts ?? 0, 0, "{$this->shortName}::maxCheckAttempts");
    }

    /**
     * @param int|null $normalCheckInterval
     *
     * @throws AssertionFailedException
     */
    public function setNormalCheckInterval(int|null $normalCheckInterval): void {
        $this->normalCheckInterval = $normalCheckInterval;
        Assertion::min($this->normalCheckInterval ?? 0, 0, "{$this->shortName}::normalCheckInterval");
    }

    /**
     * @param int|null $retryCheckInterval
     *
     * @throws AssertionFailedException
     */
    public function setRetryCheckInterval(int|null $retryCheckInterval): void {
        $this->retryCheckInterval = $retryCheckInterval;
        Assertion::min($this->retryCheckInterval ?? 0, 0, "{$this->shortName}::retryCheckInterval");
    }

    /**
     * @param int|null $notificationInterval
     *
     * @throws AssertionFailedException
     */
    public function setNotificationInterval(int|null $notificationInterval): void {
        $this->notificationInterval = $notificationInterval;
        Assertion::min($this->notificationInterval ?? 0, 0, "{$this->shortName}::notificationInterval");
    }

    /**
     * @param int|null $firstNotificationDelay
     *
     * @throws AssertionFailedException
     */
    public function setFirstNotificationDelay(int|null $firstNotificationDelay): void {
        $this->firstNotificationDelay = $firstNotificationDelay;
        Assertion::min($this->firstNotificationDelay ?? 0, 0, "{$this->shortName}::firstNotificationDelay");
    }

    /**
     * @param int|null $recoveryNotificationDelay
     *
     * @throws AssertionFailedException
     */
    public function setRecoveryNotificationDelay(int|null $recoveryNotificationDelay): void {
        $this->recoveryNotificationDelay = $recoveryNotificationDelay;
        Assertion::min($this->recoveryNotificationDelay ?? 0, 0, "{$this->shortName}::recoveryNotificationDelay");
    }

    /**
     * @param int|null $acknowledgementTimeout
     *
     * @throws AssertionFailedException
     */
    public function setAcknowledgementTimeout(int|null $acknowledgementTimeout): void {
        $this->acknowledgementTimeout = $acknowledgementTimeout;
        Assertion::min($this->acknowledgementTimeout ?? 0, 0, "{$this->shortName}::acknowledgementTimeout");
    }

    /**
     * @param int|null $freshnessThreshold
     *
     * @throws AssertionFailedException
     */
    public function setFreshnessThreshold(int|null $freshnessThreshold): void {
        $this->freshnessThreshold = $freshnessThreshold;
        Assertion::min($this->freshnessThreshold ?? 0, 0, "{$this->shortName}::freshnessThreshold");
    }

    /**
     * @param int|null $lowFlapThreshold
     *
     * @throws AssertionFailedException
     */
    public function setLowFlapThreshold(int|null $lowFlapThreshold): void {
        $this->lowFlapThreshold = $lowFlapThreshold;
        Assertion::min($this->lowFlapThreshold ?? 0, 0, "{$this->shortName}::lowFlapThreshold");
    }

    /**
     * @param int|null $highFlapThreshold
     *
     * @throws AssertionFailedException
     */
    public function setHighFlapThreshold(int|null $highFlapThreshold): void {
        $this->highFlapThreshold = $highFlapThreshold;
        Assertion::min($this->highFlapThreshold ?? 0, 0, "{$this->shortName}::highFlapThreshold");
    }

    /**
     * @param HostEvent[] $notificationOptions
     */
    public function setNotificationOptions(array $notificationOptions): void
    {
        $this->notificationOptions = $notificationOptions;
    }

    public function setActiveCheckEnabled(YesNoDefault $activeCheckEnabled): void
    {
        $this->activeCheckEnabled = $activeCheckEnabled;
    }

    public function setPassiveCheckEnabled(YesNoDefault $passiveCheckEnabled): void
    {
        $this->passiveCheckEnabled = $passiveCheckEnabled;
    }

    public function setNotificationEnabled(YesNoDefault $notificationEnabled): void
    {
        $this->notificationEnabled = $notificationEnabled;
    }

    public function setFreshnessChecked(YesNoDefault $freshnessChecked): void
    {
        $this->freshnessChecked = $freshnessChecked;
    }

    public function setFlapDetectionEnabled(YesNoDefault $flapDetectionEnabled): void
    {
        $this->flapDetectionEnabled = $flapDetectionEnabled;
    }

    public function setEventHandlerEnabled(YesNoDefault $eventHandlerEnabled): void
    {
        $this->eventHandlerEnabled = $eventHandlerEnabled;
    }

    public function setAddInheritedContactGroup(bool $addInheritedContactGroup): void
    {
        $this->addInheritedContactGroup = $addInheritedContactGroup;
    }

    public function setAddInheritedContact(bool $addInheritedContact): void
    {
        $this->addInheritedContact = $addInheritedContact;
    }

    public function setIsActivated(bool $isActivated): void
    {
        $this->isActivated = $isActivated;
    }
}
