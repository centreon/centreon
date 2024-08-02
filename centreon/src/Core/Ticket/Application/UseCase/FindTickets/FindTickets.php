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

namespace Core\Ticket\Application\UseCase\FindTickets;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Ticket\Application\Repository\ReadTicketRepositoryInterface;
use Core\Ticket\Domain\Model\Ticket;

final class FindTickets
{
    use LoggerTrait;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param ReadTicketRepositoryInterface $repository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadTicketRepositoryInterface $repository,
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    /**
     * @param FindTicketsPresenterInterface $presenter
     */
    public function __invoke(FindTicketsPresenterInterface $presenter): void
    {
        try {
            $tickets = $this->repository->findAllByRequestParameters($this->requestParameters);
            $this->info('Finding tickets', ['request_parameters' => $this->requestParameters->toArray()]);
            $presenter->presentResponse($this->createResponse($tickets));
        } catch (\Throwable $exception) {
            $this->error($exception->getTraceAsString());
            $presenter->presentResponse(new ErrorResponse('new error...'));
        }
    }

    /**
     * @param Ticket[] $tickets
     *
     * @return FindTicketsResponse
     */
    private function createResponse(array $tickets): FindTicketsResponse
    {
        $response = new FindTicketsResponse();

        $response->tickets = array_map(
            static fn (Ticket $ticket): TicketDto => new TicketDto(id: $ticket->getId(), subject: $ticket->getSubject(), createdAt: $ticket->getCreatedAt()),
            $tickets
        );

        return $response;
    }
}
