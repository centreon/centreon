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

class AddHostSaasPresenter extends AbstractPresenter implements AddHostPresenterInterface
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
                        'snmp_version' => $response->snmpVersion,
                        'geo_coords' => $response->geoCoords,
                        'icon_id' => $response->iconId,
                        'alias' => $this->emptyStringAsNull($response->alias),
                        'max_check_attempts' => $response->maxCheckAttempts,
                        'normal_check_interval' => $response->normalCheckInterval,
                        'retry_check_interval' => $response->retryCheckInterval,
                        'note_url' => $this->emptyStringAsNull($response->noteUrl),
                        'note' => $this->emptyStringAsNull($response->note),
                        'action_url' => $this->emptyStringAsNull($response->actionUrl),
                        'timezone_id' => $response->timezoneId,
                        'severity_id' => $response->severityId,
                        'check_timeperiod_id' => $response->checkTimeperiodId,
                        'event_handler_enabled' => $response->eventHandlerEnabled,
                        'event_handler_command_args' => $response->eventHandlerCommandArgs,
                        'check_command_id' => $response->checkCommandId,
                        'check_command_args' => $response->checkCommandArgs,
                        'categories' => array_map(
                            function (array $category) {
                                return [
                                    'id' => $category['id'],
                                    'name' => $category['name'],
                                ];
                            },
                            $response->categories
                        ),
                        'groups' => array_map(
                            function (array $group) {
                                return [
                                    'id' => $group['id'],
                                    'name' => $group['name'],
                                ];
                            },
                            $response->groups
                        ),
                        'templates' => array_map(
                            function (array $template) {
                                return [
                                    'id' => $template['id'],
                                    'name' => $template['name'],
                                ];
                            },
                            $response->templates
                        ),
                        'macros' => array_map(
                            function (array $macro) {
                                return [
                                    'name' => $macro['name'],
                                    'value' => $macro['isPassword'] ? null : $macro['value'],
                                    'is_password' => $macro['isPassword'],
                                    'description' => $this->emptyStringAsNull($macro['description']),
                                ];
                            },
                            $response->macros
                        ),
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }
    }
}
