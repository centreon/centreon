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
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Macro\Domain\Model\Macro;

final class AddHostFactory
{
    /**
     * @param Host $host
     * @param HostCategory[] $hostCategories
     * @param array<array{id:int,name:string}> $parentTemplates
     * @param Macro[] $macros
     * @param HostGroup[] $hostGroups
     *
     * @throws \Throwable
     *
     * @return AddHostResponse
     */
    public static function createResponse(
        Host $host,
        array $hostCategories,
        array $parentTemplates,
        array $macros,
        array $hostGroups,
    ): AddHostResponse {
        $dto = new AddHostResponse();

        $dto->id = $host->getId();
        $dto->monitoringServerId = $host->getMonitoringServerId();
        $dto->name = $host->getName();
        $dto->address = $host->getAddress();
        $dto->snmpVersion = $host->getSnmpVersion()?->value;
        $dto->geoCoords = $host->getGeoCoordinates()?->__toString();
        $dto->alias = $host->getAlias();
        $dto->snmpCommunity = $host->getSnmpCommunity();
        $dto->noteUrl = $host->getNoteUrl();
        $dto->note = $host->getNote();
        $dto->actionUrl = $host->getActionUrl();
        $dto->iconId = $host->getIconId();
        $dto->iconAlternative = $host->getIconAlternative();
        $dto->comment = $host->getComment();
        $dto->checkCommandArgs = $host->getCheckCommandArgs();
        $dto->eventHandlerCommandArgs = $host->getEventHandlerCommandArgs();
        $dto->activeCheckEnabled = YesNoDefaultConverter::toInt($host->getActiveCheckEnabled());
        $dto->passiveCheckEnabled = YesNoDefaultConverter::toInt($host->getPassiveCheckEnabled());
        $dto->notificationEnabled = YesNoDefaultConverter::toInt($host->getNotificationEnabled());
        $dto->notificationOptions = HostEventConverter::toBitFlag($host->getNotificationOptions());
        $dto->freshnessChecked = YesNoDefaultConverter::toInt($host->getFreshnessChecked());
        $dto->flapDetectionEnabled = YesNoDefaultConverter::toInt($host->getFlapDetectionEnabled());
        $dto->eventHandlerEnabled = YesNoDefaultConverter::toInt($host->getEventHandlerEnabled());
        $dto->timezoneId = $host->getTimezoneId();
        $dto->severityId = $host->getSeverityId();
        $dto->checkCommandId = $host->getCheckCommandId();
        $dto->checkTimeperiodId = $host->getCheckTimeperiodId();
        $dto->eventHandlerCommandId = $host->getEventHandlerCommandId();
        $dto->maxCheckAttempts = $host->getMaxCheckAttempts();
        $dto->normalCheckInterval = $host->getNormalCheckInterval();
        $dto->retryCheckInterval = $host->getRetryCheckInterval();
        $dto->notificationInterval = $host->getNotificationInterval();
        $dto->notificationTimeperiodId = $host->getNotificationTimeperiodId();
        $dto->firstNotificationDelay = $host->getFirstNotificationDelay();
        $dto->recoveryNotificationDelay = $host->getRecoveryNotificationDelay();
        $dto->acknowledgementTimeout = $host->getAcknowledgementTimeout();
        $dto->freshnessThreshold = $host->getFreshnessThreshold();
        $dto->lowFlapThreshold = $host->getLowFlapThreshold();
        $dto->highFlapThreshold = $host->getHighFlapThreshold();
        $dto->addInheritedContactGroup = $host->addInheritedContactGroup();
        $dto->addInheritedContact = $host->addInheritedContact();
        $dto->isActivated = $host->isActivated();

        $dto->categories = array_map(
            fn(HostCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $hostCategories
        );

        $dto->groups = array_map(
            fn(HostGroup $group) => ['id' => $group->getId(), 'name' => $group->getName()],
            $hostGroups
        );

        $dto->templates = array_map(
            fn($template) => ['id' => $template['id'], 'name' => $template['name']],
            $parentTemplates
        );

        $dto->macros = array_map(
            static fn(Macro $macro): array => [
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ],
            $macros
        );

        return $dto;
    }
}
