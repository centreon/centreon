<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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
use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriod;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriodPresenter;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\AddTimePeriodRequest;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\DayDto;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\TemplateDto;
use Symfony\Component\HttpFoundation\Request;

class AddTimePeriodController extends AbstractController
{
    public function __invoke(
        Request $request,
        AddTimePeriod $useCase,
        AddTimePeriodPresenter $presenter,
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        try {
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
     * @return AddTimePeriodRequest
     */
    private function createDtoRequest(array $dataSent): AddTimePeriodRequest
    {
        $dto = new AddTimePeriodRequest();
        $dto->id = $dataSent['id'];
        $dto->name = $dataSent['name'];
        $dto->alias = $dataSent['alias'];
        $dto->days = array_map(function (array $day): array {
            return [
                'day' => $day['day'],
                'time_range' => $day['time_range']
            ];
        }, $dataSent['days']);
        $dto->templates = $dataSent['templates'];
        $dto->exceptions = array_map(function (array $exception): array {
            return [
                'day_range' => $exception['day_range'],
                'time_range' => $exception['time_range'],
            ];
        }, $dataSent['exceptions']);
        return $dto;
    }
}
