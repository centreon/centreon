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
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Gorgone\Application\Exception\CollectorEnrolmentException;
use Core\Gorgone\Application\Service\CollectorEnrolmentService;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lists the enrolment tokens (never the tokens themselves, which nobody keeps): kind, expiry, uses.
 */
final class FindCollectorEnrolmentTokensController extends AbstractController
{
    public function __construct(private readonly CollectorEnrolmentService $service)
    {
    }

    #[Route(path: '/configuration/collectors/enrolment-tokens', name: 'FindCollectorEnrolmentTokens', methods: ['GET'])]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message: 'You must be an admin to list collector enrolment tokens',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(#[CurrentUser] ContactInterface $currentUser): Response
    {
        if (! $this->service->isEnabled()) {
            return CollectorEnrolmentResponses::notFound();
        }
        try {
            $now = (int) (microtime(true) * 1000);
            $pollerNames = $this->service->findPollerNames();
            $result = [];
            foreach ($this->service->findTokens() as $token) {
                $result[] = [
                    'id' => $token->id,
                    'kind' => $token->isFleet() ? 'fleet' : 'named',
                    'poller_id' => $token->pollerId,
                    'poller_name' => $token->pollerId !== null ? ($pollerNames[$token->pollerId] ?? null) : null,
                    'name' => $token->name,
                    'expires_at' => FindCollectorEnrolmentsController::isoDate($token->expiresAt),
                    'expired' => $token->expiresAt <= $now,
                    'max_uses' => $token->maxUses,
                    'uses' => $token->uses,
                    'spent' => $token->isSpent($now),
                    'approval_required' => $token->approvalRequired,
                    'created_at' => FindCollectorEnrolmentsController::isoDate($token->createdAt),
                    'last_used_at' => $token->lastUsedAt !== null
                        ? FindCollectorEnrolmentsController::isoDate($token->lastUsedAt)
                        : null,
                ];
            }

            return new JsonResponse(['result' => $result]);
        } catch (CollectorEnrolmentException $ex) {
            return CollectorEnrolmentResponses::error(Response::HTTP_BAD_REQUEST, $ex->getMessage());
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex);

            return CollectorEnrolmentResponses::error(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Unable to list the enrolment tokens'
            );
        }
    }
}
