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

namespace Core\Service\Infrastructure\API\FindServices;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Service\Application\UseCase\FindServices\FindServicesPresenterInterface;
use Core\Service\Application\UseCase\FindServices\FindServicesResponse;

class FindServicesOnPremPresenter extends AbstractPresenter implements FindServicesPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|FindServicesResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];
            foreach ($response->services as $dto) {
                $result[] = [
                    'id' => $dto->id,
                    'name' => $dto->name,
                    'hosts' => array_map(fn($host): array => [
                        'id' => $host['id'],
                        'name' => $host['name'],
                    ], $dto->hosts),
                    'service_template' => $dto->serviceTemplate
                        ? ['id' => $dto->serviceTemplate['id'], 'name' => $dto->serviceTemplate['name']]
                        : null,
                    'check_timeperiod' => $dto->checkTimePeriod
                        ? ['id' => $dto->checkTimePeriod['id'], 'name' => $dto->checkTimePeriod['name']]
                        : null,
                    'notification_timeperiod' => $dto->notificationTimePeriod
                        ? ['id' => $dto->notificationTimePeriod['id'], 'name' => $dto->notificationTimePeriod['name']]
                        : null,
                    'severity' => $dto->severity
                        ? ['id' => $dto->severity['id'], 'name' => $dto->severity['name']]
                        : null,
                    'categories' => array_map(fn($category): array => [
                        'id' => $category['id'],
                        'name' => $category['name'],
                    ], $dto->categories),
                    'groups' => array_map(fn($group): array => [
                        'id' => $group['id'],
                        'name' => $group['name'],
                        'host_id' => $group['hostId'],
                        'host_name' => $group['hostName'],
                    ], $dto->groups),
                    'normal_check_interval' => $dto->normalCheckInterval,
                    'retry_check_interval' => $dto->retryCheckInterval,
                    'is_activated' => $dto->isActivated,
                ];
            }
            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
