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

namespace Core\TimePeriod\Application\UseCase\FindTimePeriods;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\TimePeriod;
use Core\TimePeriod\Domain\Model\Day;

class FindTimePeriods
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param RequestParametersInterface $requestParameters
     */
    public function __construct(
        private ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private RequestParametersInterface $requestParameters,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @return void
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            $this->info('Find the time periods');
            $this->debug('Request', ['parameters' => $this->requestParameters->getSearch()]);
            $timePeriods = $this->readTimePeriodRepository->findByRequestParameter($this->requestParameters);
            $presenter->present($this->createResponse($timePeriods));
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse('Error while searching for the time periods'));
            $this->error(
                'Error while searching for the time periods',
                ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
        }
    }

    /**
     * @param TimePeriod[] $timePeriods
     * @return FindTimePeriodsResponse
     */
    private function createResponse(array $timePeriods): FindTimePeriodsResponse
    {
        $response = new FindTimePeriodsResponse();
        foreach ($timePeriods as $timePeriod) {
            $response->timePeriods[] = [
                'id' => $timePeriod->getId(),
                'name' => $timePeriod->getName(),
                'alias' => $timePeriod->getAlias(),
                'days' => array_map(function (Day $day) {
                    return [
                        'day' => $day->getDay(),
                        'time_range' => (string) $day->getTimeRange()
                    ];
                }, $timePeriod->getDays()),
                'templates' => array_map(function (Template $template) {
                    return [
                        'id' => $template->getId(),
                        'alias' => $template->getAlias(),
                    ];
                }, $timePeriod->getTemplates()),
                'exceptions' => array_map(function (ExtraTimePeriod $exception) {
                    return [
                        'id' => $exception->getId(),
                        'day_range' => $exception->getDayRange(),
                        'time_range' => (string) $exception->getTimeRange(),
                    ];
                }, $timePeriod->getExtraTimePeriods())
            ];
        }
        return $response;
    }
}
