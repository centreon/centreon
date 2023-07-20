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

namespace Core\Host\Application\UseCase\AddHost;

use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\NewHost;
use Core\Host\Domain\Model\SnmpVersion;

final class NewHostFactory
{
    /**
     * @param AddHostRequest $dto
     * @param int $inheritanceMode
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
     *
     * @return NewHost
     */
    public static function create(AddHostRequest $dto, int $inheritanceMode): NewHost
    {
        return new NewHost(
            name: $dto->name,
            address: $dto->address,
            monitoringServerId: $dto->monitoringServerId,
            alias: $dto->alias,
            snmpVersion: $dto->snmpVersion === ''
                ? null
                : SnmpVersion::from($dto->snmpVersion),
            snmpCommunity: $dto->snmpCommunity,
            noteUrl: $dto->noteUrl,
            note: $dto->note,
            actionUrl: $dto->actionUrl,
            iconId: $dto->iconId,
            iconAlternative: $dto->iconAlternative,
            comment: $dto->comment,
            geoCoordinates: $dto->geoCoordinates === ''
                ? null
                : GeoCoords::fromString($dto->geoCoordinates),
            notificationOptions: $dto->notificationOptions === null
                ? []
                : HostEventConverter::fromBitFlag($dto->notificationOptions),
            checkCommandArgs: $dto->checkCommandArgs,
            eventHandlerCommandArgs: $dto->eventHandlerCommandArgs,
            timezoneId: $dto->timezoneId,
            severityId: $dto->severityId,
            checkCommandId: $dto->checkCommandId,
            checkTimeperiodId: $dto->checkTimeperiodId,
            notificationTimeperiodId: $dto->notificationTimeperiodId,
            eventHandlerCommandId: $dto->eventHandlerCommandId,
            maxCheckAttempts: $dto->maxCheckAttempts,
            normalCheckInterval: $dto->normalCheckInterval,
            retryCheckInterval: $dto->retryCheckInterval,
            notificationInterval: $dto->notificationInterval,
            firstNotificationDelay: $dto->firstNotificationDelay,
            recoveryNotificationDelay: $dto->recoveryNotificationDelay,
            acknowledgementTimeout: $dto->acknowledgementTimeout,
            freshnessThreshold: $dto->freshnessThreshold,
            lowFlapThreshold: $dto->lowFlapThreshold,
            highFlapThreshold: $dto->highFlapThreshold,
            activeCheckEnabled: YesNoDefaultConverter::fromScalar($dto->activeCheckEnabled),
            passiveCheckEnabled: YesNoDefaultConverter::fromScalar($dto->passiveCheckEnabled),
            notificationEnabled: YesNoDefaultConverter::fromScalar($dto->notificationEnabled),
            freshnessChecked: YesNoDefaultConverter::fromScalar($dto->freshnessChecked),
            flapDetectionEnabled: YesNoDefaultConverter::fromScalar($dto->flapDetectionEnabled),
            eventHandlerEnabled: YesNoDefaultConverter::fromScalar($dto->eventHandlerEnabled),
            addInheritedContactGroup: $inheritanceMode === 1 ? $dto->addInheritedContactGroup : false,
            addInheritedContact: $inheritanceMode === 1 ? $dto->addInheritedContact : false,
            isActivated: $dto->isActivated,
        );
    }
}
