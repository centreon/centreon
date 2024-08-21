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

namespace Core\HostTemplate\Infrastructure\API\AddHostTemplate;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplatePresenterInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostTemplateOnPremPresenter extends AbstractPresenter implements AddHostTemplatePresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(AddHostTemplateResponse|ResponseStatusInterface $response): void
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
                        'alias' => $response->alias,
                        'snmp_version' => $response->snmpVersion,
                        'timezone_id' => $response->timezoneId,
                        'severity_id' => $response->severityId,
                        'check_command_id' => $response->checkCommandId,
                        'check_command_args' => $response->checkCommandArgs,
                        'check_timeperiod_id' => $response->checkTimeperiodId,
                        'max_check_attempts' => $response->maxCheckAttempts,
                        'normal_check_interval' => $response->normalCheckInterval,
                        'retry_check_interval' => $response->retryCheckInterval,
                        'active_check_enabled' => $response->activeCheckEnabled,
                        'passive_check_enabled' => $response->passiveCheckEnabled,
                        'notification_enabled' => $response->notificationEnabled,
                        'notification_options' => $response->notificationOptions,
                        'notification_interval' => $response->notificationInterval,
                        'notification_timeperiod_id' => $response->notificationTimeperiodId,
                        'add_inherited_contact_group' => $response->addInheritedContactGroup,
                        'add_inherited_contact' => $response->addInheritedContact,
                        'first_notification_delay' => $response->firstNotificationDelay,
                        'recovery_notification_delay' => $response->recoveryNotificationDelay,
                        'acknowledgement_timeout' => $response->acknowledgementTimeout,
                        'freshness_checked' => $response->freshnessChecked,
                        'freshness_threshold' => $response->freshnessThreshold,
                        'flap_detection_enabled' => $response->flapDetectionEnabled,
                        'low_flap_threshold' => $response->lowFlapThreshold,
                        'high_flap_threshold' => $response->highFlapThreshold,
                        'event_handler_enabled' => $response->eventHandlerEnabled,
                        'event_handler_command_id' => $response->eventHandlerCommandId,
                        'event_handler_command_args' => $response->eventHandlerCommandArgs,
                        'note_url' => $this->emptyStringAsNull($response->noteUrl),
                        'note' => $this->emptyStringAsNull($response->note),
                        'action_url' => $this->emptyStringAsNull($response->actionUrl),
                        'icon_id' => $response->iconId,
                        'icon_alternative' => $this->emptyStringAsNull($response->iconAlternative),
                        'comment' => $this->emptyStringAsNull($response->comment),
                        'is_locked' => $response->isLocked,
                        'categories' => $response->categories,
                        'templates' => $response->templates,
                        'macros' => array_map(
                            fn($macro) => [
                                'name' => $macro['name'],
                                'value' => $macro['isPassword'] ? null : $macro['value'],
                                'is_password' => $macro['isPassword'],
                                'description' => $this->emptyStringAsNull($macro['description']),
                            ],
                            $response->macros
                        ),
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }

    }
}
