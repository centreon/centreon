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
 * Lists the enrolments made by collectors (never a token): pending fleet ones, enrolled, revoked.
 */
final class FindCollectorEnrolmentsController extends AbstractController
{
    public function __construct(private readonly CollectorEnrolmentService $service)
    {
    }

    #[Route(path: '/configuration/collectors/enrolments', name: 'FindCollectorEnrolments', methods: ['GET'])]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message: 'You must be an admin to list collector enrolments',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(#[CurrentUser] ContactInterface $currentUser): Response
    {
        if (! $this->service->isEnabled()) {
            return new JsonResponse(['code' => 404, 'message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        try {
            $result = [];
            foreach ($this->service->findEnrolments() as $enrolment) {
                $result[] = [
                    'id' => $enrolment->id,
                    'state' => $enrolment->state,
                    'poller_id' => $enrolment->pollerId,
                    'name' => $enrolment->name,
                    'address' => $enrolment->address,
                    'agent_version' => $enrolment->agentVersion,
                ];
            }

            return new JsonResponse(['result' => $result]);
        } catch (CollectorEnrolmentException $ex) {
            return new JsonResponse(['code' => 400, 'message' => $ex->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex);

            return new JsonResponse(
                ['code' => 500, 'message' => 'Unable to list the enrolments'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
