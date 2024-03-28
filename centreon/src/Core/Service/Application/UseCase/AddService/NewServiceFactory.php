<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Application\UseCase\AddService;

use Assert\AssertionFailedException;
use Core\Domain\Common\GeoCoords;
use Core\Service\Application\Model\NotificationTypeConverter;
use Core\Service\Application\Model\YesNoDefaultConverter;
use Core\Service\Domain\Model\NewService;

class NewServiceFactory
{
    /**
     * @param int $inheritanceMode
     * @param AddServiceRequest $request
     * @param bool $isCloudPlatform
     *
     * @throws AssertionFailedException
     *
     * @return NewService
     */
    public static function create(int $inheritanceMode, AddServiceRequest $request, bool $isCloudPlatform): NewService
    {
        $newService = new NewService($request->name, $request->hostId, $request->commandId);
        foreach ($request->commandArguments as $argument) {
            $newService->addCommandArgument($argument);
        }

        foreach ($request->eventHandlerArguments as $argument) {
            $newService->addEventHandlerArgument($argument);
        }

        if ($request->notificationTypes !== null) {
            foreach (NotificationTypeConverter::fromBits($request->notificationTypes) as $notificationType) {
                $newService->addNotificationType($notificationType);
            }
        }

        $newService->setContactAdditiveInheritance(
            ($inheritanceMode === 1) ? $request->isContactAdditiveInheritance : false
        );

        $newService->setContactGroupAdditiveInheritance(
            ($inheritanceMode === 1) ? $request->isContactGroupAdditiveInheritance : false
        );

        $newService->setActivated($request->isActivated);
        $newService->setActiveChecks(YesNoDefaultConverter::fromInt($request->activeChecks));
        $newService->setPassiveCheck(YesNoDefaultConverter::fromInt($request->passiveCheck));
        $newService->setVolatility(YesNoDefaultConverter::fromInt($request->volatility));
        $newService->setCheckFreshness(YesNoDefaultConverter::fromInt($request->checkFreshness));
        $newService->setEventHandlerEnabled(YesNoDefaultConverter::fromInt($request->eventHandlerEnabled));
        $newService->setFlapDetectionEnabled(YesNoDefaultConverter::fromInt($request->flapDetectionEnabled));
        $newService->setNotificationsEnabled(YesNoDefaultConverter::fromInt($request->notificationsEnabled));
        $newService->setComment($request->comment);
        $newService->setNote($request->note);
        $newService->setNoteUrl($request->noteUrl);
        $newService->setActionUrl($request->actionUrl);
        $newService->setIconAlternativeText($request->iconAlternativeText);
        $newService->setGraphTemplateId($request->graphTemplateId);
        $newService->setServiceTemplateParentId($request->serviceTemplateParentId);
        $newService->setEventHandlerId($request->eventHandlerId);
        $newService->setNotificationTimePeriodId($request->notificationTimePeriodId);
        $newService->setCheckTimePeriodId($request->checkTimePeriodId);
        $newService->setIconId($request->iconId);
        $newService->setSeverityId($request->severityId);
        $newService->setMaxCheckAttempts($request->maxCheckAttempts);
        $newService->setNormalCheckInterval($request->normalCheckInterval);
        $newService->setRetryCheckInterval($request->retryCheckInterval);
        $newService->setFreshnessThreshold($request->freshnessThreshold);
        $newService->setLowFlapThreshold($request->lowFlapThreshold);
        $newService->setHighFlapThreshold($request->highFlapThreshold);
        $newService->setNotificationInterval($request->notificationInterval);
        $newService->setRecoveryNotificationDelay($request->recoveryNotificationDelay);
        $newService->setFirstNotificationDelay($request->firstNotificationDelay);
        $newService->setAcknowledgementTimeout($request->acknowledgementTimeout);
        $newService->setGeoCoords($request->geoCoords === ''
            ? null
            : GeoCoords::fromString($request->geoCoords)
        );

        return $newService;
    }
}
