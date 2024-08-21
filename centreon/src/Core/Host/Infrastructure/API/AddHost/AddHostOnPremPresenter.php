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

namespace Core\Host\Infrastructure\API\AddHost;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Host\Application\UseCase\AddHost\AddHostPresenterInterface;
use Core\Host\Application\UseCase\AddHost\AddHostResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostOnPremPresenter extends AbstractPresenter implements AddHostPresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(AddHostResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'monitoring_server_id' => $response->monitoringServerId,
                        'name' => $response->name,
                        'address' => $response->address,
                        'alias' => $this->emptyStringAsNull($response->alias),
                        'snmp_version' => $response->snmpVersion,
                        'geo_coords' => $response->geoCoords,
                        'note_url' => $this->emptyStringAsNull($response->noteUrl),
                        'note' => $this->emptyStringAsNull($response->note),
                        'action_url' => $this->emptyStringAsNull($response->actionUrl),
                        'icon_alternative' => $this->emptyStringAsNull($response->iconAlternative),
                        'comment' => $this->emptyStringAsNull($response->comment),
                        'timezone_id' => $response->timezoneId,
                        'severity_id' => $response->severityId,
                        'check_command_id' => $response->checkCommandId,
                        'check_timeperiod_id' => $response->checkTimeperiodId,
                        'notification_timeperiod_id' => $response->notificationTimeperiodId,
                        'event_handler_command_id' => $response->eventHandlerCommandId,
                        'icon_id' => $response->iconId,
                        'max_check_attempts' => $response->maxCheckAttempts,
                        'normal_check_interval' => $response->normalCheckInterval,
                        'retry_check_interval' => $response->retryCheckInterval,
                        'notification_options' => $response->notificationOptions,
                        'notification_interval' => $response->notificationInterval,
                        'first_notification_delay' => $response->firstNotificationDelay,
                        'recovery_notification_delay' => $response->recoveryNotificationDelay,
                        'acknowledgement_timeout' => $response->acknowledgementTimeout,
                        'freshness_threshold' => $response->freshnessThreshold,
                        'low_flap_threshold' => $response->lowFlapThreshold,
                        'high_flap_threshold' => $response->highFlapThreshold,
                        'freshness_checked' => $response->freshnessChecked,
                        'active_check_enabled' => $response->activeCheckEnabled,
                        'passive_check_enabled' => $response->passiveCheckEnabled,
                        'notification_enabled' => $response->notificationEnabled,
                        'flap_detection_enabled' => $response->flapDetectionEnabled,
                        'event_handler_enabled' => $response->eventHandlerEnabled,
                        'check_command_args' => $response->checkCommandArgs,
                        'event_handler_command_args' => $response->eventHandlerCommandArgs,
                        'categories' => array_map(
                            fn(array $category) => [
                                'id' => $category['id'],
                                'name' => $category['name'],
                            ],
                            $response->categories
                        ),
                        'groups' => array_map(
                            fn(array $group) => [
                                'id' => $group['id'],
                                'name' => $group['name'],
                            ],
                            $response->groups
                        ),
                        'templates' => array_map(
                            fn(array $template) => [
                                'id' => $template['id'],
                                'name' => $template['name'],
                            ],
                            $response->templates
                        ),
                        'macros' => array_map(
                            fn(array $macro) => [
                                'name' => $macro['name'],
                                'value' => $macro['isPassword'] ? null : $macro['value'],
                                'is_password' => $macro['isPassword'],
                                'description' => $this->emptyStringAsNull($macro['description']),
                            ],
                            $response->macros
                        ),
                        'add_inherited_contact_group' => $response->addInheritedContactGroup,
                        'add_inherited_contact' => $response->addInheritedContact,
                        'is_activated' => $response->isActivated,
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }

    }
}
