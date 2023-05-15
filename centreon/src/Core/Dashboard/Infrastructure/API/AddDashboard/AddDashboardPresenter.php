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

namespace Core\Dashboard\Infrastructure\API\AddDashboard;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardPresenterInterface;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Api\Router;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

final class AddDashboardPresenter extends DefaultPresenter implements AddDashboardPresenterInterface
{
    use PresenterTrait;
    use LoggerTrait;
    private const ROUTE_NAME = 'FindDashboard';

    /**
     * @param PresenterFormatterInterface $presenterFormatter
     * @param Router $router
     */
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
        readonly private Router $router
    ) {
        $this->presenterFormatter = $presenterFormatter;
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(AddDashboardResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof AddDashboardResponse) {
            $this->present(
                new CreatedResponse(
                    $data->id,
                    [
                        'id' => $data->id,
                        'name' => $data->name,
                        'description' => $this->emptyStringAsNull($data->description),
                        'created_at' => $this->formatDateToIso8601($data->createdAt),
                        'updated_at' => $this->formatDateToIso8601($data->updatedAt),
                    ]
                )
            );

            try {
                $this->setResponseHeaders([
                    'Location' => $this->router->generate(self::ROUTE_NAME, ['id' => $data->id]),
                ]);
            } catch (\Throwable $ex) {
                $this->error('Impossible to generate the location header', [
                    'message' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString(),
                    'route' => self::ROUTE_NAME,
                    'payload' => $data,
                ]);
            }
        } else {
            $this->setResponseStatus($data);
        }
    }
}
