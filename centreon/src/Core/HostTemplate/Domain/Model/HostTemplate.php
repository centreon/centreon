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
    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param null|SnmpVersion $snmpVersion
     * @param string $snmpCommunity
     * @param null|int $timezoneId
     * @param null|int $severityId
     * @param null|int $checkCommandId
     * @param string $checkCommandArgs
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
     * @param string $eventHandlerCommandArgs
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
        string $checkCommandArgs = '',
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
        string $eventHandlerCommandArgs = '',
        string $noteUrl = '',
        string $note = '',
        string $actionUrl = '',
        ?int $iconId = null,
        string $iconAlternative = '',
        string $comment = '',
        bool $isActivated = true,
        bool $isLocked = false
    ) {
        Assertion::positiveInt($id, 'HostTemplate::id');

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
}
