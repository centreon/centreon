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

namespace Core\TimePeriod\Infrastructure\API\AddTimePeriod;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\TimePeriod\Application\UseCase\AddTimePeriod\{AddTimePeriod, AddTimePeriodDto, AddTimePeriodRequest};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

final class AddTimePeriodController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param AddTimePeriod $useCase
     * @param AddTimePeriodRequest $input
     * @param AddTimePeriodsPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        AddTimePeriod $useCase,
        #[MapRequestPayload] AddTimePeriodRequest $input,
        AddTimePeriodsPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $useCase($this->createDto($input), $presenter);

        return $presenter->show();
    }

    private function createDto(AddTimePeriodRequest $data): AddTimePeriodDto
    {
        return new AddTimePeriodDto(
            is_string($data->name) ? $data->name : '',
            is_string($data->alias) ? $data->alias : '',
            array_map(
                fn (array $day): array => ['day' => $day['day'], 'time_range' => $day['time_range']],
                $data->days ?? []
            ),
            $data->templates ?? [],
            array_map(
                fn (array $exception): array => [
                    'day_range' => $exception['day_range'],
                    'time_range' => $exception['time_range'],
                ],
                $data->exceptions ?? []
            ),
        );
    }
}
