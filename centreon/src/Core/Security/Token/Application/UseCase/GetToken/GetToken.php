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

namespace Core\Security\Token\Application\UseCase\GetToken;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

final class GetToken
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(string $tokenName, ?int $userId = null): GetTokenResponse|ResponseStatusInterface
    {
        try {
            $token = $this->readTokenRepository->findByNameAndUserId(
                $tokenName,
                $userId ?? $this->user->getId()
            );
            $tokenString = $this->readTokenRepository->findTokenString(
                $tokenName,
                $userId ?? $this->user->getId()
            );

            if (
                $token === null
                || $tokenString === null
                || $token->getType() !== TokenTypeEnum::CMA
            ) {

                return new NotFoundResponse('Token');
            }

            return new GetTokenResponse($token, $tokenString);
        } catch (\Throwable $ex) {
            $this->error(
                "Error while retrieving a token: {$ex->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'token_name' => $tokenName,
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(TokenException::errorWhileRetrievingObject()->getMessage());
        }
    }
}
