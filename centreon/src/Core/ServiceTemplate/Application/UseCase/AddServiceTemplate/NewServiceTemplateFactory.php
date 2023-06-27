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

namespace Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

use Assert\AssertionFailedException;
use Core\ServiceTemplate\Application\Model\NotificationTypeConverter;
use Core\ServiceTemplate\Application\Model\YesNoDefaultConverter;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;

class NewServiceTemplateFactory
{
    /**
     * @param int $inheritanceMode
     * @param AddServiceTemplateRequest $request
     *
     * @throws AssertionFailedException
     *
     * @return NewServiceTemplate
     */
    public static function create(int $inheritanceMode, AddServiceTemplateRequest $request): NewServiceTemplate
    {
        $serviceTemplate = new NewServiceTemplate($request->name, $request->alias);
        foreach ($request->commandArguments as $argument) {
            $serviceTemplate->addCommandArgument($argument);
        }

        foreach ($request->eventHandlerArguments as $argument) {
            $serviceTemplate->addEventHandlerArgument($argument);
        }

        if ($request->notificationTypes !== null) {
            foreach (NotificationTypeConverter::fromBits($request->notificationTypes) as $notificationType) {
                $serviceTemplate->addNotificationType($notificationType);
            }
        }

        $serviceTemplate->setContactAdditiveInheritance(
            ($inheritanceMode === 1) ? $request->isContactAdditiveInheritance : false
        );

        $serviceTemplate->setContactGroupAdditiveInheritance(
            ($inheritanceMode === 1) ? $request->isContactGroupAdditiveInheritance : false
        );

        $serviceTemplate->setActivated($request->isActivated);
        $serviceTemplate->setLocked(false);
        $serviceTemplate->setActiveChecks(YesNoDefaultConverter::fromInt($request->activeChecks));
        $serviceTemplate->setPassiveCheck(YesNoDefaultConverter::fromInt($request->passiveCheck));
        $serviceTemplate->setVolatility(YesNoDefaultConverter::fromInt($request->volatility));
        $serviceTemplate->setCheckFreshness(YesNoDefaultConverter::fromInt($request->checkFreshness));
        $serviceTemplate->setEventHandlerEnabled(YesNoDefaultConverter::fromInt($request->eventHandlerEnabled));
        $serviceTemplate->setFlapDetectionEnabled(YesNoDefaultConverter::fromInt($request->flapDetectionEnabled));
        $serviceTemplate->setNotificationsEnabled(YesNoDefaultConverter::fromInt($request->notificationsEnabled));
        $serviceTemplate->setComment($request->comment);
        $serviceTemplate->setNote($request->note);
        $serviceTemplate->setNoteUrl($request->noteUrl);
        $serviceTemplate->setActionUrl($request->actionUrl);
        $serviceTemplate->setIconAlternativeText($request->iconAlternativeText);
        $serviceTemplate->setGraphTemplateId($request->graphTemplateId);
        $serviceTemplate->setServiceTemplateParentId($request->serviceTemplateParentId);
        $serviceTemplate->setCommandId($request->commandId);
        $serviceTemplate->setEventHandlerId($request->eventHandlerId);
        $serviceTemplate->setNotificationTimePeriodId($request->notificationTimePeriodId);
        $serviceTemplate->setCheckTimePeriodId($request->checkTimePeriodId);
        $serviceTemplate->setIconId($request->iconId);
        $serviceTemplate->setSeverityId($request->severityId);
        $serviceTemplate->setMaxCheckAttempts($request->maxCheckAttempts);
        $serviceTemplate->setNormalCheckInterval($request->normalCheckInterval);
        $serviceTemplate->setRetryCheckInterval($request->retryCheckInterval);
        $serviceTemplate->setFreshnessThreshold($request->freshnessThreshold);
        $serviceTemplate->setLowFlapThreshold($request->lowFlapThreshold);
        $serviceTemplate->setHighFlapThreshold($request->highFlapThreshold);
        $serviceTemplate->setNotificationInterval($request->notificationInterval);
        $serviceTemplate->setRecoveryNotificationDelay($request->recoveryNotificationDelay);
        $serviceTemplate->setFirstNotificationDelay($request->firstNotificationDelay);
        $serviceTemplate->setAcknowledgementTimeout($request->acknowledgementTimeout);
        $serviceTemplate->setHostTemplateIds($request->hostTemplateIds);

        return $serviceTemplate;
    }
}
