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
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Service\Application\UseCase\AddService\AddServicePresenterInterface;
use Core\Service\Application\UseCase\AddService\AddServiceResponse;
use Core\Service\Application\UseCase\AddService\MacroDto;

class AddServiceSaasPresenter extends AbstractPresenter implements AddServicePresenterInterface
{
    public function __construct(PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

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
                        'service_template_id' => $response->serviceTemplateId,
                        'check_timeperiod_id' => $response->checkTimePeriodId,
                        'max_check_attempts' => $response->maxCheckAttempts,
                        'normal_check_interval' => $response->normalCheckInterval,
                        'retry_check_interval' => $response->retryCheckInterval,
                        'note' => $response->note,
                        'note_url' => $response->noteUrl,
                        'action_url' => $response->actionUrl,
                        'geo_coords' => $response->geoCoords,
                        'icon_id' => $response->iconId,
                        'severity_id' => $response->severityId,
                        'categories' => array_map(fn($category): array => [
                            'id' => $category['id'],
                            'name' => $category['name'],
                        ], $response->categories),
                        'groups' => array_map(fn($group): array => [
                            'id' => $group['id'],
                            'name' => $group['name'],
                        ], $response->groups),
                        'macros' => array_map(fn(MacroDto $macro): array => [
                            'name' => $macro->name,
                            'value' => $macro->isPassword ? null : $macro->value,
                            'is_password' => $macro->isPassword,
                            'description' => $macro->description,
                        ], $response->macros),
                    ]
                )
            );
        }
    }
}
