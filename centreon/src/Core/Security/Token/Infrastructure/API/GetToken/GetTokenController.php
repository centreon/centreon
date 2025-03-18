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

namespace Core\Security\Token\Infrastructure\API\GetToken;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Core\Security\Token\Application\UseCase\GetToken\GetToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetTokenController extends AbstractController
{
    #[IsGranted(
        'token_list',
        'userId',
        'You are not allowed to access API tokens',
        Response::HTTP_FORBIDDEN
    )]
    public function __invoke(
        StandardPresenter $presenter,
        GetToken $useCase,
        string $tokenName,
        ?int $userId = null,
    ): Response {

        $response = $useCase($tokenName, $userId);

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString($presenter->present(
            $response,
            ['groups' => ['Token:Get']]
        ));
    }
}
