<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\API\CollectorEnrolment;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Gorgone\Application\Service\CollectorEnrolmentService;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Approves or rejects the enrolment of a machine with a fleet token: approved, the cron creates its
 * poller; rejected, the collector is refused and gets no poller.
 */
final class DecideCollectorEnrolmentController extends AbstractController
{
    public function __construct(private readonly CollectorEnrolmentService $service)
    {
    }

    #[Route(
        path: '/configuration/collectors/enrolments/{enrolmentId}/{decision}',
        name: 'DecideCollectorEnrolment',
        requirements: ['enrolmentId' => '[0-9a-zA-Z_-]{1,191}', 'decision' => 'approve|reject'],
        methods: ['POST']
    )]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message: 'You must be an admin to approve or reject collector enrolments',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(
        string $enrolmentId,
        string $decision,
        #[CurrentUser] ContactInterface $currentUser,
    ): Response {
        if (! $this->service->isEnabled()) {
            return CollectorEnrolmentResponses::notFound();
        }

        return CollectorEnrolmentResponses::run(
            fn () => $decision === 'approve'
                ? $this->service->approve($enrolmentId)
                : $this->service->reject($enrolmentId),
            sprintf('Unable to %s the enrolment', $decision)
        );
    }
}
