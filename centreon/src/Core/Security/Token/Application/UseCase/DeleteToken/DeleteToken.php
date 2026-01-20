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

namespace Core\Security\Token\Application\UseCase\DeleteToken;

use Adaptation\Log\LoggerToken;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\ApiToken;
use Core\Security\Token\Domain\Model\JwtToken;
use Core\Security\Token\Domain\Model\Token;

final class DeleteToken
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param string $tokenName
     * @param int $userId
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter, string $tokenName, ?int $userId = null): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW)) {
                ExceptionLogger::create()->log(
                    TokenException::deleteNotAllowed(),
                    [
                        'message' => 'User doesn\'t have sufficient rights to delete a token',
                        'user_id' => $this->user->getId(),
                        'token_name' => $tokenName,
                    ]
                );

                LoggerToken::create()->warning(
                    event: 'deletion',
                    reason: 'insufficient rights',
                    userId: $this->user->getId(),
                    tokenName: $tokenName
                );

                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::deleteNotAllowed())
                );

                return;
            }

            $userId ??= $this->user->getId();

            if (! ($token = $this->readTokenRepository->findByNameAndUserId($tokenName, $userId))) {
                ExceptionLogger::create()->log(
                    TokenException::tokenNotFound(),
                    [
                        'message' => 'Token not found',
                        'user_id' => $this->user->getId(),
                        'token_name' => $tokenName,
                    ]
                );

                LoggerToken::create()->warning(
                    event: 'deletion',
                    reason: 'not found',
                    userId: $this->user->getId(),
                    tokenName: $tokenName
                );

                $presenter->setResponseStatus(new NotFoundResponse('Token'));

                return;
            }

            if (! $this->canUserDeleteToken($this->user, $token)) {
                ExceptionLogger::create()->log(
                    TokenException::notAllowedToDeleteTokenForUser($userId),
                    [
                        'message' => 'Not allowed to delete token linked to user who isn\'t the requester',
                        'token_name' => $tokenName,
                        'token_type' => $token->getType()->name,
                        'user_id' => $this->user->getId(),
                    ]
                );

                LoggerToken::create()->warning(
                    event: 'deletion',
                    reason: 'not allowed to delete token for user',
                    userId: $this->user->getId(),
                    tokenName: $tokenName,
                    tokenType: $token->getType()->name
                );

                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::notAllowedToDeleteTokenForUser($userId))
                );

                return;
            }

            $this->writeTokenRepository->deleteByNameAndUserId($tokenName, $userId);

            LoggerToken::create()->success(
                event: 'deletion',
                userId: $this->user->getId(),
                tokenName: $tokenName,
                tokenType: $token->getType()->name
            );

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log(
                $ex,
                [
                    'user_id' => $this->user->getId(),
                    'token_name' => $tokenName,
                    'token_type' => isset($token) ? $token->getType()->name : null,
                ]
            );

            LoggerToken::create()->warning(
                event: 'deletion',
                reason: 'unexpected error',
                userId: $this->user->getId(),
                tokenName: $tokenName,
                tokenType: isset($token) ? $token->getType()->name : null,
                exception: $ex
            );

            $presenter->setResponseStatus(
                new ErrorResponse(TokenException::deleteToken())
            );
        }
    }

    private function canUserDeleteToken(
        ContactInterface $user,
        Token $token,
    ): bool {
        return (bool) (
            $user->isAdmin()
            || $user->hasRole(Contact::ROLE_MANAGE_TOKENS)
            || ($token instanceof ApiToken && $token->getUserId() === $user->getId())
            || ($token instanceof JwtToken && $token->getCreatorId() === $user->getId())
        );
    }
}
