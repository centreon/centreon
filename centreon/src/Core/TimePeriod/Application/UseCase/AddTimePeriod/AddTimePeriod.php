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

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Centreon\Domain\Log\LoggerTrait;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimePeriod;
use Core\Application\Common\UseCase\{CreatedResponse, ErrorResponse, PresenterInterface};

class AddTimePeriod
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param WriteTimePeriodRepositoryInterface $writeTimePeriodRepository
     */
    public function __construct(
        readonly private ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        readonly private WriteTimePeriodRepositoryInterface $writeTimePeriodRepository
    ) {
    }

    /**
     * @param AddTimePeriodRequest $request
     * @param PresenterInterface $presenter
     * @return void
     */
    public function __invoke(AddTimePeriodRequest $request, PresenterInterface $presenter): void
    {
        try {
            $this->info('Add a new time period', ['request' => $request]);

            if ($this->readTimePeriodRepository->nameAlreadyExists($request->name)) {
                $this->error('Time period name already exists');
                $presenter->setResponseStatus(
                    new ErrorResponse(TimePeriodException::nameAlreadyExists($request->name)->getMessage())
                );
                return;
            }
            $newTimePeriod = NewTimePeriodFactory::create($request);
            $newTimePeriodId = $this->writeTimePeriodRepository->add($newTimePeriod);
            $timePeriod = $this->readTimePeriodRepository->findById($newTimePeriodId);
            if ($timePeriod === null) {
                throw new \Exception('Impossible to retrieve the time period when it has just been created');
            }
            $presenter->present(
                new CreatedResponse($newTimePeriodId, $this->createResponse($timePeriod))
            );
        } catch (\Throwable $ex) {
            $this->error(
                'Error when adding the time period',
                ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(TimePeriodException::errorWhenAddingTimePeriod()->getMessage())
            );
        }
    }

    /**
     * @param TimePeriod $timePeriod
     * @return AddTimePeriodResponse
     */
    private function createResponse(TimePeriod $timePeriod): AddTimePeriodResponse
    {
        $response = new AddTimePeriodResponse();
        $response->id = $timePeriod->getId();
        $response->name = $timePeriod->getName();
        $response->alias = $timePeriod->getAlias();
        $response->days = array_map(function (Day $day) {
            return [
                'day' => $day->getDay(),
                'time_range' => (string)$day->getTimeRange()
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
                'time_range' => (string)$exception->getTimeRange(),
            ];
        }, $timePeriod->getExtraTimePeriods());
        return $response;
    }
}
