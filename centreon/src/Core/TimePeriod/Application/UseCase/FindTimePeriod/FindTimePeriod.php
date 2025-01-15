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

namespace Core\TimePeriod\Application\UseCase\FindTimePeriod;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{ErrorResponse,
    ForbiddenResponse,
    NotFoundResponse,
    ResponseStatusInterface};
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;

final class FindTimePeriod
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param int $timePeriodId
     *
     * @return FindTimePeriodResponse|ResponseStatusInterface
     */
    public function __invoke(int $timePeriodId): FindTimePeriodResponse|ResponseStatusInterface
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ)
            ) {
                $this->error('User doesn\'t have sufficient rights to see time periods', [
                    'user_id' => $this->user->getId(),
                ]);

                return new ForbiddenResponse(TimePeriodException::accessNotAllowed()->getMessage());
            }
            $this->info('Find a time period', ['id' => $timePeriodId]);
            $timePeriod = $this->readTimePeriodRepository->findById($timePeriodId);
            if ($timePeriod === null) {
                $this->error('Time period not found', ['id' => $timePeriodId]);

                return new NotFoundResponse('Time period');
            }

            return new FindTimePeriodResponse($timePeriod);
        } catch (\Throwable $ex) {
            $this->error(
                'Error when searching for the time period',
                ['id' => $timePeriodId, 'message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );

            return new ErrorResponse(TimePeriodException::errorWhenSearchingForTimePeriod($timePeriodId)->getMessage());
        }
    }
}
