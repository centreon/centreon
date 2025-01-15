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

namespace Core\Service\Infrastructure\API\DeployServices;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Service\Application\UseCase\DeployServices\DeployServiceDto;
use Core\Service\Application\UseCase\DeployServices\DeployServicesPresenterInterface;
use Core\Service\Application\UseCase\DeployServices\DeployServicesResponse;
use Core\Service\Infrastructure\Model\NotificationTypeConverter;
use Core\Service\Infrastructure\Model\YesNoDefaultConverter;

class DeployServicesOnPremPresenter extends AbstractPresenter implements DeployServicesPresenterInterface
{
    /**
     * @inheritDoc
     */
    public function presentResponse(DeployServicesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    null,
                    [
                        'services' => array_map(
                            static fn (DeployServiceDto $service): array => [
                                'id' => $service->id,
                                'name' => $service->name,
                                'host_id' => $service->hostId,
                                'geo_coords' => $service->geoCoords,
                                'comment' => $service->comment,
                                'service_template_id' => $service->serviceTemplateId,
                                'check_command_id' => $service->commandId,
                                'check_command_args' => $service->commandArguments,
                                'check_timeperiod_id' => $service->checkTimePeriodId,
                                'max_check_attempts' => $service->maxCheckAttempts,
                                'normal_check_interval' => $service->normalCheckInterval,
                                'retry_check_interval' => $service->retryCheckInterval,
                                'active_check_enabled' => YesNoDefaultConverter::toInt($service->activeChecksEnabled),
                                'passive_check_enabled' => YesNoDefaultConverter::toInt($service->passiveChecksEnabled),
                                'volatility_enabled' => YesNoDefaultConverter::toInt($service->volatilityEnabled),
                                'notification_enabled' => YesNoDefaultConverter::toInt($service->notificationsEnabled),
                                'is_contact_additive_inheritance' => $service->isContactAdditiveInheritance,
                                'is_contact_group_additive_inheritance' => $service->isContactGroupAdditiveInheritance,
                                'notification_timeperiod_id' => $service->notificationTimePeriodId,
                                'notification_type' => NotificationTypeConverter::toBits($service->notificationTypes),
                                'first_notification_delay' => $service->firstNotificationDelay,
                                'recovery_notification_delay' => $service->recoveryNotificationDelay,
                                'acknowledgement_timeout' => $service->acknowledgementTimeout,
                                'freshness_checked' => YesNoDefaultConverter::toInt($service->checkFreshness),
                                'freshness_threshold' => $service->freshnessThreshold,
                                'flap_detection_enabled' => YesNoDefaultConverter::toInt($service->flapDetectionEnabled),
                                'low_flap_threshold' => $service->lowFlapThreshold,
                                'high_flap_threshold' => $service->highFlapThreshold,
                                'event_handler_enabled' => YesNoDefaultConverter::toInt($service->eventHandlerEnabled),
                                'event_handler_command_id' => $service->eventHandlerCommandId,
                                'event_handler_command_args' => $service->eventHandlerArguments,
                                'graph_template_id' => $service->graphTemplateId,
                                'note' => $service->note,
                                'note_url' => $service->noteUrl,
                                'action_url' => $service->actionUrl,
                                'icon_id' => $service->iconId,
                                'icon_alternative' => $service->iconAlternative,
                                'severity_id' => $service->severityId,
                                'is_activated' => $service->isActivated,
                            ],
                            $response->services
                        ),
                    ]
                )
            );
        }
    }
}
