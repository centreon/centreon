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

class DeployServicesSaasPresenter extends AbstractPresenter implements DeployServicesPresenterInterface
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
                                'service_template_id' => $service->serviceTemplateId,
                                'check_timeperiod_id' => $service->checkTimePeriodId,
                                'max_check_attempts' => $service->maxCheckAttempts,
                                'normal_check_interval' => $service->normalCheckInterval,
                                'retry_check_interval' => $service->retryCheckInterval,
                                'note' => $service->note,
                                'note_url' => $service->noteUrl,
                                'action_url' => $service->actionUrl,
                                'icon_id' => $service->iconId,
                                'severity_id' => $service->severityId,
                            ],
                            $response->services
                        ),
                    ]
                )
            );
        }
    }
}
