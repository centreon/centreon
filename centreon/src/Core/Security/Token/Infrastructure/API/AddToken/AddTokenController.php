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

namespace Core\Security\Token\Infrastructure\API\AddToken;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\UseCase\AddToken\AddToken;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenRequest;
use Core\Security\Token\Infrastructure\Voters\TokenVoters;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(
    TokenVoters::TOKEN_ADD,
    null,
    'You are not allowed to create token',
    Response::HTTP_FORBIDDEN
)]
final class AddTokenController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param AddTokenInput $request
     * @param AddToken $useCase
     * @param StandardPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        #[MapRequestPayload()] AddTokenInput $request,
        AddToken $useCase,
        StandardPresenter $presenter,
    ): Response {
        $response = $useCase(AddTokenRequestTransformer::transform($request));

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString(
            $presenter->present(
                $response,
                [
                    'groups' => ['Token:List'],
                ]
            ),
            Response::HTTP_CREATED
        );
    }
}
