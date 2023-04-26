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

namespace Core\TimePeriod\Application\UseCase\FindTimePeriods;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimePeriod;

final class FindTimePeriods
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        readonly private RequestParametersInterface $requestParameters,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ)
            ) {
                $this->error('User doesn\'t have sufficient rights to see time periods', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TimeperiodException::accessNotAllowed()->getMessage())
                );

                return;
            }
            $this->info('Find the time periods', ['parameters' => $this->requestParameters->getSearch()]);
            $timePeriods = $this->readTimePeriodRepository->findByRequestParameter($this->requestParameters);
            $presenter->present($this->createResponse($timePeriods));
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(TimePeriodException::errorWhenSearchingForAllTimePeriods()->getMessage())
            );
            $this->error(
                'Error while searching for time periods',
                ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
        }
    }

    /**
     * @param TimePeriod[] $timePeriods
     *
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
                'days' => array_map(
                    fn (Day $day): array => [
                        'day' => $day->getDay(),
                        'time_range' => (string) $day->getTimeRange(),
                    ],
                    $timePeriod->getDays()
                ),
                'templates' => array_map(
                    fn (Template $template): array => [
                        'id' => $template->getId(),
                        'alias' => $template->getAlias(),
                    ],
                    $timePeriod->getTemplates()
                ),
                'exceptions' => array_map(
                    fn (ExtraTimePeriod $exception): array => [
                        'id' => $exception->getId(),
                        'day_range' => $exception->getDayRange(),
                        'time_range' => (string) $exception->getTimeRange(),
                    ],
                    $timePeriod->getExtraTimePeriods()
                ),
            ];
        }

        return $response;
    }
}
