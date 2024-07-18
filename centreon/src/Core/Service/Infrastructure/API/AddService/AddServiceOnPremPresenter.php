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

namespace Core\Service\Infrastructure\API\AddService;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Service\Application\UseCase\AddService\AddServicePresenterInterface;
use Core\Service\Application\UseCase\AddService\AddServiceResponse;
use Core\Service\Application\UseCase\AddService\MacroDto;
use Core\Service\Infrastructure\Model\NotificationTypeConverter;
use Core\Service\Infrastructure\Model\YesNoDefaultConverter;

class AddServiceOnPremPresenter extends AbstractPresenter implements AddServicePresenterInterface
{
    public function presentResponse(ResponseStatusInterface|AddServiceResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'name' => $response->name,
                        'host_id' => $response->hostId,
                        'comment' => $response->comment,
                        'service_template_id' => $response->serviceTemplateId,
                        'check_command_id' => $response->commandId,
                        'check_command_args' => $response->commandArguments,
                        'check_timeperiod_id' => $response->checkTimePeriodId,
                        'max_check_attempts' => $response->maxCheckAttempts,
                        'normal_check_interval' => $response->normalCheckInterval,
                        'retry_check_interval' => $response->retryCheckInterval,
                        'active_check_enabled' => YesNoDefaultConverter::toInt($response->activeChecks),
                        'passive_check_enabled' => YesNoDefaultConverter::toInt($response->passiveCheck),
                        'volatility_enabled' => YesNoDefaultConverter::toInt($response->volatility),
                        'notification_enabled' => YesNoDefaultConverter::toInt($response->notificationsEnabled),
                        'is_contact_additive_inheritance' => $response->isContactAdditiveInheritance,
                        'is_contact_group_additive_inheritance' => $response->isContactGroupAdditiveInheritance,
                        'notification_interval' => $response->notificationInterval,
                        'notification_timeperiod_id' => $response->notificationTimePeriodId,
                        'notification_type' => NotificationTypeConverter::toBits($response->notificationTypes),
                        'first_notification_delay' => $response->firstNotificationDelay,
                        'recovery_notification_delay' => $response->recoveryNotificationDelay,
                        'acknowledgement_timeout' => $response->acknowledgementTimeout,
                        'freshness_checked' => YesNoDefaultConverter::toInt($response->checkFreshness),
                        'freshness_threshold' => $response->freshnessThreshold,
                        'flap_detection_enabled' => YesNoDefaultConverter::toInt($response->flapDetectionEnabled),
                        'low_flap_threshold' => $response->lowFlapThreshold,
                        'high_flap_threshold' => $response->highFlapThreshold,
                        'event_handler_enabled' => YesNoDefaultConverter::toInt($response->eventHandlerEnabled),
                        'event_handler_command_id' => $response->eventHandlerId,
                        'event_handler_command_args' => $response->eventHandlerArguments,
                        'graph_template_id' => $response->graphTemplateId,
                        'note' => $response->note,
                        'note_url' => $response->noteUrl,
                        'action_url' => $response->actionUrl,
                        'icon_id' => $response->iconId,
                        'icon_alternative' => $response->iconAlternativeText,
                        'geo_coords' => $response->geoCoords,
                        'severity_id' => $response->severityId,
                        'is_activated' => $response->isActivated,
                        'macros' => array_map(fn(MacroDto $macro): array => [
                            'name' => $macro->name,
                            'value' => $macro->isPassword ? null : $macro->value,
                            'is_password' => $macro->isPassword,
                            'description' => $macro->description,
                        ], $response->macros),
                        'categories' => array_map(fn($category): array => [
                            'id' => $category['id'],
                            'name' => $category['name'],
                        ], $response->categories),
                        'groups' => array_map(fn($group): array => [
                            'id' => $group['id'],
                            'name' => $group['name'],
                        ], $response->groups),
                    ]
                )
            );

            // The location will be implemented when the FindServiceTemplate API endpoint is implemented.
        }
    }
}
