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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Creates an enrolment and answers its token, once, with the command to run on the collector.
 *
 * Body: {"name": "poller-name"} (creates the poller), {"poller_id": 12} (existing poller) or
 * {"fleet": true, "max_uses": 10, "approval_required": true}; optional "expires_in" in seconds (24 hours by
 * default).
 */
final class AddCollectorEnrolmentController extends AbstractController
{
    public function __construct(private readonly CollectorEnrolmentService $service)
    {
    }

    #[Route(path: '/configuration/collectors/enrolments', name: 'AddCollectorEnrolment', methods: ['POST'])]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message: 'You must be an admin to enrol collectors',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(Request $request, #[CurrentUser] ContactInterface $currentUser): Response
    {
        if (! $this->service->isEnabled()) {
            return new JsonResponse(['code' => 404, 'message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $body = json_decode((string) $request->getContent(), true);
        if (! is_array($body)) {
            return self::error(Response::HTTP_BAD_REQUEST, 'The body must be a JSON object');
        }
        $integer = static fn (string $key): ?int => is_int($body[$key] ?? null) ? $body[$key] : null;
        foreach (['poller_id', 'expires_in', 'max_uses'] as $key) {
            if (isset($body[$key]) && ! is_int($body[$key])) {
                return self::error(Response::HTTP_BAD_REQUEST, sprintf('%s must be an integer', $key));
            }
        }
        if ((isset($body['name']) && ! is_string($body['name'])) || (isset($body['fleet']) && ! is_bool($body['fleet']))) {
            return self::error(Response::HTTP_BAD_REQUEST, 'name must be a string and fleet a boolean');
        }
        if (isset($body['approval_required']) && ! is_bool($body['approval_required'])) {
            return self::error(Response::HTTP_BAD_REQUEST, 'approval_required must be a boolean');
        }

        try {
            $created = $this->service->create(
                name: $body['name'] ?? null,
                pollerId: $integer('poller_id'),
                isFleet: ($body['fleet'] ?? false) === true,
                expiresIn: $integer('expires_in'),
                maxUses: $integer('max_uses'),
                creatorId: (int) $currentUser->getId(),
                creatorName: (string) $currentUser->getAlias(),
                approvalRequired: ($body['approval_required'] ?? false) === true,
            );
        } catch (CollectorEnrolmentException $ex) {
            return self::error(Response::HTTP_BAD_REQUEST, $ex->getMessage());
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex);

            return self::error(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create the enrolment: ' . $ex->getMessage());
        }

        $response = new JsonResponse([
            'id' => $created->id,
            'type' => $created->isFleet ? 'fleet' : 'named',
            'poller_id' => $created->pollerId,
            'poller_name' => $created->pollerName,
            'expires_at' => (new \DateTimeImmutable('@' . $created->expiresAt))->format(\DateTimeInterface::ATOM),
            'max_uses' => $created->maxUses,
            'approval_required' => $created->approvalRequired,
            // Shown once: only its hash is kept, by gorgone-server.
            'token' => $created->token,
            'environment' => $created->environment,
            'command' => $created->command,
        ], Response::HTTP_CREATED);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private static function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $status, 'message' => $message], $status);
    }
}
