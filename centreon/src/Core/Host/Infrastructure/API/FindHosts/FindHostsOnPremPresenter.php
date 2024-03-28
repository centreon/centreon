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

declare(strict_types = 1);

namespace Core\Host\Infrastructure\API\FindHosts;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsPresenterInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsResponse;
use Core\Host\Application\UseCase\FindHosts\SimpleDto;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

final class FindHostsOnPremPresenter extends AbstractPresenter implements FindHostsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindHostsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        }
        else {
            $result = [];
            foreach ($response->hostDto as $dto) {
                $result[] = [
                    'id' => $dto->id,
                    'name' => $dto->name,
                    'alias' => $this->emptyStringAsNull($dto->alias ?? ''),
                    'address' => $dto->ipAddress,
                    'monitoring_server' => [
                        'id' => $dto->poller->id,
                        'name' => $dto->poller->name,
                    ],
                    'templates' => array_map(
                        fn (SimpleDto $template) => ['id' => $template->id, 'name' => $template->name],
                        $dto->templateParents
                    ),
                    'normal_check_interval' => $dto->normalCheckInterval,
                    'retry_check_interval' => $dto->retryCheckInterval,
                    'notification_timeperiod' => $dto->notificationTimeperiod !== null
                        ? [
                            'id' => $dto->notificationTimeperiod->id,
                            'name' => $dto->notificationTimeperiod->name,
                        ]: null,
                    'check_timeperiod' => $dto->checkTimeperiod !== null
                        ? [
                            'id' => $dto->checkTimeperiod->id,
                            'name' => $dto->checkTimeperiod->name,
                        ]: null,
                    'severity' => $dto->severity !== null
                        ? ['id' => $dto->severity->id, 'name' => $dto->severity->name]
                        : null,
                    'categories' => array_map(
                        fn (SimpleDto $category) => ['id' => $category->id, 'name' => $category->name],
                        $dto->categories
                    ),
                    'groups' => array_map(
                        fn (SimpleDto $group) => ['id' => $group->id, 'name' => $group->name],
                        $dto->groups
                    ),
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
