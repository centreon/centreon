<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\TimePeriod\Infrastructure\API\AddTimePeriod;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Infrastructure\Common\Api\Router;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\{
    AddTimePeriod, AddTimePeriodRequest
};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AddTimePeriodController extends AbstractController
{
    /**
     * @param Request $request
     * @param AddTimePeriod $useCase
     * @param AddTimePeriodsPresenter $presenter
     * @param Router $router
     *
     * @throws AccessDeniedException
     *
     * @return object
     */
    public function __invoke(
        Request $request,
        AddTimePeriod $useCase,
        AddTimePeriodsPresenter $presenter,
        Router $router
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        try {
            /**
             * @var array{
             *     name: string,
             *     alias: string,
             *     days: array<array{
             *         day: integer,
             *         time_range: string
             *     }>,
             *     templates: int[],
             *     exceptions: array<array{
             *         day_range: string,
             *         time_range: string
             *     }>
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddTimePeriodSchema.json');
            $dtoRequest = $this->createDtoRequest($dataSent);
            $useCase($dtoRequest, $presenter);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse($ex->getMessage())
            );
        }

        return $presenter->show();
    }

    /**
     * @param array{
     *     name: string,
     *     alias: string,
     *     days: array<array{
     *         day: integer,
     *         time_range: string
     *     }>,
     *     templates: int[],
     *     exceptions: array<array{
     *         day_range: string,
     *         time_range: string
     *     }>
     * } $dataSent
     *
     * @return AddTimePeriodRequest
     */
    private function createDtoRequest(array $dataSent): AddTimePeriodRequest
    {
        $dto = new AddTimePeriodRequest();
        $dto->name = $dataSent['name'];
        $dto->alias = $dataSent['alias'];
        $dto->days = array_map(
            fn (array $day): array => ['day' => $day['day'], 'time_range' => $day['time_range']],
            $dataSent['days']
        );
        $dto->templates = $dataSent['templates'];
        $dto->exceptions = array_map(
            fn (array $exception): array => [
                'day_range' => $exception['day_range'],
                'time_range' => $exception['time_range'],
            ],
            $dataSent['exceptions']
        );

        return $dto;
    }
}
