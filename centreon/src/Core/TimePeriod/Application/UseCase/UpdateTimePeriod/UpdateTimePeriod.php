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

namespace Core\TimePeriod\Application\UseCase\UpdateTimePeriod;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\{
    ConflictResponse,
    ErrorResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse,
    PresenterInterface
};
use Core\Common\Domain\TrimmedString;
use Core\TimePeriod\Application\Exception\TimePeriodException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Application\Repository\WriteTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Exception\TimeRangeException;
use Core\TimePeriod\Domain\Model\{Day, ExtraTimePeriod, Template, TimePeriod, TimeRange};

final class UpdateTimePeriod
{
    use LoggerTrait;

    public function __construct(
        readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        readonly WriteTimePeriodRepositoryInterface $writeTimePeriodRepository,
        readonly ContactInterface $user
    ) {
    }

    /**
     * @param UpdateTimePeriodRequest $request
     * @param PresenterInterface $presenter
     */
    public function __invoke(UpdateTimePeriodRequest $request, PresenterInterface $presenter): void
    {
        $this->info('Updating the time period', ['request' => $request]);
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE)) {
                $this->error('User doesn\'t have sufficient rights to edit time periods', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TimePeriodException::editNotAllowed()->getMessage())
                );

                return;
            }
            if (($timePeriod = $this->readTimePeriodRepository->findById($request->id)) === null) {
                $this->error('Time period not found', ['id' => $request->id]);
                $presenter->setResponseStatus(new NotFoundResponse('Time period'));

                return;
            }
            if ($this->readTimePeriodRepository->nameAlreadyExists(new TrimmedString($request->name), $request->id)) {
                $this->error('Time period name already exists');
                $presenter->setResponseStatus(
                    new ConflictResponse(TimePeriodException::nameAlreadyExists($request->name))
                );

                return;
            }
            $this->updateTimePeriodAndSave($timePeriod, $request);
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (AssertionFailedException|TimeRangeException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error(
                'Error when updating the time period',
                ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(TimePeriodException::errorOnUpdate($request->id))
            );
        }
    }

    /**
     * @param TimePeriod $timePeriod
     * @param UpdateTimePeriodRequest $request
     *
     * @throws AssertionFailedException
     * @throws \Throwable
     */
    private function updateTimePeriodAndSave(TimePeriod $timePeriod, UpdateTimePeriodRequest $request): void
    {
        $timePeriod->setName($request->name);
        $timePeriod->setAlias($request->alias);
        $timePeriod->setDays(
            array_map(
                fn (array $day): Day => new Day(
                    $day['day'],
                    new TimeRange($day['time_range']),
                ),
                $request->days
            )
        );
        $timePeriod->setTemplates(
            array_map(
                fn (int $templateId): Template => new Template(
                    $templateId,
                    'name_not_used'
                ),
                $request->templates
            )
        );
        $timePeriod->setExtraTimePeriods(
            array_map(
                fn (array $exception): ExtraTimePeriod => new ExtraTimePeriod(
                    1,
                    $exception['day_range'],
                    new TimeRange($exception['time_range'])
                ),
                $request->exceptions
            )
        );
        $this->writeTimePeriodRepository->update($timePeriod);
    }
}
