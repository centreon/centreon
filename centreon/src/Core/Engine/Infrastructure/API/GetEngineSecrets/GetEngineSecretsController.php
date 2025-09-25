<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Engine\Infrastructure\API\GetEngineSecrets;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Engine\Application\Exception\EngineSecretsBadFormatException;
use Core\Engine\Application\Exception\EngineSecretsDoesNotExistException;
use Core\Engine\Application\Repository\EngineRepositoryInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

final class GetEngineSecretsController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EngineRepositoryInterface $engineRepository,
    ) {
    }

    #[Route(path: '/administration/engine/secrets', name: 'GetEngineSecrets', methods: ['GET'])]
    #[IsGranted(
        new Expression('subject.isAdmin() === true'),
        subject: new Expression('args["currentUser"]'),
        message:'You must be an admin to access engine secrets',
        statusCode: Response::HTTP_FORBIDDEN
    )]
    public function __invoke(#[CurrentUser] ContactInterface $currentUser): Response
    {
        try {
            $engineSecrets = $this->engineRepository->getEngineSecrets();

            return JsonResponse::fromJsonString(
                $this->serializer->serialize($engineSecrets, 'json')
            );
        } catch (EngineSecretsDoesNotExistException|EngineSecretsBadFormatException $ex) {
            ExceptionLogger::create()->log($ex);

            return $this->createResponse(new ErrorResponse($ex->getMessage()));
        } catch (AssertionException $ex) {
            ExceptionLogger::create()->log($ex);

            return $this->createResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex);

            return $this->createResponse(new ErrorResponse('Unable to retrieve engine secrets'));
        }
    }
}
