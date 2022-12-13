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
            $this->writeTimePeriodRepository->add($newTimePeriod);
            $presenter->setResponseStatus(new CreatedResponse());
        } catch (\Throwable $ex) {
            $this->error(
                'Error when adding the time period',
                ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(new ErrorResponse('Error'));
        }
    }
}
