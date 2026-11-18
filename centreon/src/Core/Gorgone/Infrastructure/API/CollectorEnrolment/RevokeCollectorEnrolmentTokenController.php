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
 * Revokes an enrolment token: it enrols nothing more. Collectors it already enrolled keep working.
 */
final class RevokeCollectorEnrolmentTokenController extends AbstractController
{
    public function __construct(private readonly CollectorEnrolmentService $service)
    {
    }

    #[Route(
        path: '/configuration/collectors/enrolment-tokens/{tokenId}',
        name: 'RevokeCollectorEnrolmentToken',
        requirements: ['tokenId' => '[0-9a-zA-Z_-]{1,191}'],
        methods: ['DELETE']
    )]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message: 'You must be an admin to revoke collector enrolment tokens',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(string $tokenId, #[CurrentUser] ContactInterface $currentUser): Response
    {
        if (! $this->service->isEnabled()) {
            return CollectorEnrolmentResponses::notFound();
        }

        return CollectorEnrolmentResponses::run(
            fn () => $this->service->revokeToken($tokenId),
            'Unable to revoke the token'
        );
    }
}
