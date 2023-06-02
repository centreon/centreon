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

namespace Core\Dashboard\Infrastructure\API\FindDashboards;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboardsPresenterInterface;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboardsResponse;
use Core\Dashboard\Application\UseCase\FindDashboards\Response\UserResponseDto;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

final class FindDashboardsPresenter extends DefaultPresenter implements FindDashboardsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindDashboardsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof FindDashboardsResponse) {
            $result = [];
            foreach ($data->dashboards as $dashboard) {
                $result[] = [
                    'id' => $dashboard->id,
                    'name' => $dashboard->name,
                    'description' => $this->emptyStringAsNull($dashboard->description),
                    'created_by' => $this->userToOptionalArray($dashboard->createdBy),
                    'updated_by' => $this->userToOptionalArray($dashboard->updatedBy),
                    'created_at' => $this->formatDateToIso8601($dashboard->createdAt),
                    'updated_at' => $this->formatDateToIso8601($dashboard->updatedAt),
                ];
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }

    /**
     * @param ?\Core\Dashboard\Application\UseCase\FindDashboards\Response\UserResponseDto $dto
     *
     * @return null|array{id: int, name: string}
     */
    private function userToOptionalArray(?UserResponseDto $dto): ?array
    {
        return $dto ? [
            'id' => $dto->id,
            'name' => $dto->name,
        ] : null;
    }
}
