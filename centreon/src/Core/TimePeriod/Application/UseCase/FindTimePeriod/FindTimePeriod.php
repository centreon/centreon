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

namespace Core\TimePeriod\Application\UseCase\FindTimePeriod;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    ErrorResponse, NotFoundResponse, PresenterInterface
};
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\{
    Day, ExtraTimePeriod, Template, TimePeriod
};

class FindTimePeriod
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     */
    public function __construct(readonly private ReadTimePeriodRepositoryInterface $readTimePeriodRepository)
    {
    }

    /**
     * @param int $timePeriodId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $timePeriodId, PresenterInterface $presenter): void
    {
        try {
            $this->info('Find a time period', ['id' => $timePeriodId]);
            $timePeriod = $this->readTimePeriodRepository->findById($timePeriodId);
            if ($timePeriod === null) {
                $this->error('Time period not found', ['id' => $timePeriodId]);
                $presenter->setResponseStatus(new NotFoundResponse('Time period'));

                return;
            }
            $presenter->present($this->createResponse($timePeriod));
        } catch (\Throwable $ex) {
            $this->error(
                'Error when searching for the time period',
                ['id' => $timePeriodId, 'message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(TimePeriodException::errorWhenSearchingForTimePeriod($timePeriodId)->getMessage())
            );
        }
    }

    /**
     * @param TimePeriod $timePeriod
     *
     * @return FindTimePeriodResponse
     */
    private function createResponse(TimePeriod $timePeriod): FindTimePeriodResponse
    {
        $response = new FindTimePeriodResponse();
        $response->id = $timePeriod->getId();
        $response->name = $timePeriod->getName();
        $response->alias = $timePeriod->getAlias();
        $response->days = array_map(function (Day $day) {
            return [
                'day' => $day->getDay(),
                'time_range' => (string) $day->getTimeRange(),
            ];
        }, $timePeriod->getDays());
        $response->templates = array_map(function (Template $template) {
            return [
                'id' => $template->getId(),
                'alias' => $template->getAlias(),
            ];
        }, $timePeriod->getTemplates());
        $response->exceptions = array_map(function (ExtraTimePeriod $exception) {
            return [
                'id' => $exception->getId(),
                'day_range' => $exception->getDayRange(),
                'time_range' => (string) $exception->getTimeRange(),
            ];
        }, $timePeriod->getExtraTimePeriods());

        return $response;
    }
}
