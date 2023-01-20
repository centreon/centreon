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

namespace Core\TimePeriod\Application\UseCase\DeleteTimePeriod;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ {
    ErrorResponse, NoContentResponse, NotFoundResponse, ForbiddenResponse,
    PresenterInterface
};
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\{
    ReadTimePeriodRepositoryInterface, WriteTimePeriodRepositoryInterface
};

class DeleteTimePeriod
{
    use LoggerTrait;

    /**
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param WriteTimePeriodRepositoryInterface $writeTimePeriodRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        readonly private WriteTimePeriodRepositoryInterface $writeTimePeriodRepository,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param int $timePeriodId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $timePeriodId, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE)) {
                $this->error('User doesn\'t have sufficient rights to edit time periods', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TimeperiodException::editNotAllowed()->getMessage())
                );

                return;
            }

            $this->info('Delete a time period', ['id' => $timePeriodId]);
            if (! $this->readTimePeriodRepository->exists($timePeriodId)) {
                $this->error('Time period not found', ['id' => $timePeriodId]);
                $presenter->setResponseStatus(new NotFoundResponse('Time period'));

                return;
            }

            $this->writeTimePeriodRepository->delete($timePeriodId);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error(
                'Error when deleting the time period',
                ['id' => $timePeriodId, 'message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(TimePeriodException::errorOnDelete($timePeriodId)->getMessage())
            );
        }
    }
}
