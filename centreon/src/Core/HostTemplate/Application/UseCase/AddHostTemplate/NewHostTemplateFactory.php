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

namespace Core\HostTemplate\Application\UseCase\AddHostTemplate;

use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostTemplate\Domain\Model\NewHostTemplate;

final class NewHostTemplateFactory
{
    /**
     * @param AddHostTemplateRequest $dto
     * @param int $inheritanceMode
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
     *
     * @return NewHostTemplate
     */
    public static function create(AddHostTemplateRequest $dto, int $inheritanceMode): NewHostTemplate
    {
        return new NewHostTemplate(
            $dto->name,
            $dto->alias,
            $dto->snmpVersion === ''
                ? null
                : SnmpVersion::from($dto->snmpVersion),
            $dto->snmpCommunity,
            $dto->timezoneId,
            $dto->severityId,
            $dto->checkCommandId,
            $dto->checkCommandArgs,
            $dto->checkTimeperiodId,
            $dto->maxCheckAttempts,
            $dto->normalCheckInterval,
            $dto->retryCheckInterval,
            YesNoDefaultConverter::fromScalar($dto->activeCheckEnabled),
            YesNoDefaultConverter::fromScalar($dto->passiveCheckEnabled),
            YesNoDefaultConverter::fromScalar($dto->notificationEnabled),
            $dto->notificationOptions === null
                ? []
                : HostEventConverter::fromBitFlag($dto->notificationOptions),
            $dto->notificationInterval,
            $dto->notificationTimeperiodId,
            $inheritanceMode === 1 ? $dto->addInheritedContactGroup : false,
            $inheritanceMode === 1 ? $dto->addInheritedContact : false,
            $dto->firstNotificationDelay,
            $dto->recoveryNotificationDelay,
            $dto->acknowledgementTimeout,
            YesNoDefaultConverter::fromScalar($dto->freshnessChecked),
            $dto->freshnessThreshold,
            YesNoDefaultConverter::fromScalar($dto->flapDetectionEnabled),
            $dto->lowFlapThreshold,
            $dto->highFlapThreshold,
            YesNoDefaultConverter::fromScalar($dto->eventHandlerEnabled),
            $dto->eventHandlerCommandId,
            $dto->eventHandlerCommandArgs,
            $dto->noteUrl,
            $dto->note,
            $dto->actionUrl,
            $dto->iconId,
            $dto->iconAlternative,
            $dto->comment,
            $dto->isActivated,
        );
    }
}
