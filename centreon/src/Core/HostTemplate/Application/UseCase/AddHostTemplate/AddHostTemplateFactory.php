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
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Domain\Model\Macro;

final class AddHostTemplateFactory
{
    /**
     * @param HostTemplate $hostTemplate
     * @param HostCategory[] $hostCategories
     * @param array<array{id:int,name:string}> $parentTemplates
     * @param Macro[] $macros
     *
     * @throws \Throwable
     *
     * @return AddHostTemplateResponse
     */
    public static function createResponse(
        HostTemplate $hostTemplate,
        array $hostCategories,
        array $parentTemplates,
        array $macros,
    ): AddHostTemplateResponse {
        $dto = new AddHostTemplateResponse();

        $dto->id = $hostTemplate->getId();
        $dto->name = $hostTemplate->getName();
        $dto->alias = $hostTemplate->getAlias();
        $dto->snmpVersion = $hostTemplate->getSnmpVersion()?->value;
        $dto->snmpCommunity = $hostTemplate->getSnmpCommunity();
        $dto->timezoneId = $hostTemplate->getTimezoneId();
        $dto->severityId = $hostTemplate->getSeverityId();
        $dto->checkCommandId = $hostTemplate->getCheckCommandId();
        $dto->checkCommandArgs = $hostTemplate->getCheckCommandArgs();
        $dto->checkTimeperiodId = $hostTemplate->getCheckTimeperiodId();
        $dto->maxCheckAttempts = $hostTemplate->getMaxCheckAttempts();
        $dto->normalCheckInterval = $hostTemplate->getNormalCheckInterval();
        $dto->retryCheckInterval = $hostTemplate->getRetryCheckInterval();
        $dto->activeCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getActiveCheckEnabled());
        $dto->passiveCheckEnabled = YesNoDefaultConverter::toInt($hostTemplate->getPassiveCheckEnabled());
        $dto->notificationEnabled = YesNoDefaultConverter::toInt($hostTemplate->getNotificationEnabled());
        $dto->notificationOptions = HostEventConverter::toBitFlag($hostTemplate->getNotificationOptions());
        $dto->notificationInterval = $hostTemplate->getNotificationInterval();
        $dto->notificationTimeperiodId = $hostTemplate->getNotificationTimeperiodId();
        $dto->addInheritedContactGroup = $hostTemplate->addInheritedContactGroup();
        $dto->addInheritedContact = $hostTemplate->addInheritedContact();
        $dto->firstNotificationDelay = $hostTemplate->getFirstNotificationDelay();
        $dto->recoveryNotificationDelay = $hostTemplate->getRecoveryNotificationDelay();
        $dto->acknowledgementTimeout = $hostTemplate->getAcknowledgementTimeout();
        $dto->freshnessChecked = YesNoDefaultConverter::toInt($hostTemplate->getFreshnessChecked());
        $dto->freshnessThreshold = $hostTemplate->getFreshnessThreshold();
        $dto->flapDetectionEnabled = YesNoDefaultConverter::toInt($hostTemplate->getFlapDetectionEnabled());
        $dto->lowFlapThreshold = $hostTemplate->getLowFlapThreshold();
        $dto->highFlapThreshold = $hostTemplate->getHighFlapThreshold();
        $dto->eventHandlerEnabled = YesNoDefaultConverter::toInt($hostTemplate->getEventHandlerEnabled());
        $dto->eventHandlerCommandId = $hostTemplate->getEventHandlerCommandId();
        $dto->eventHandlerCommandArgs = $hostTemplate->getEventHandlerCommandArgs();
        $dto->noteUrl = $hostTemplate->getNoteUrl();
        $dto->note = $hostTemplate->getNote();
        $dto->actionUrl = $hostTemplate->getActionUrl();
        $dto->iconId = $hostTemplate->getIconId();
        $dto->iconAlternative = $hostTemplate->getIconAlternative();
        $dto->comment = $hostTemplate->getComment();
        $dto->isLocked = $hostTemplate->isLocked();

        $dto->categories = array_map(
            fn(HostCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $hostCategories
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
