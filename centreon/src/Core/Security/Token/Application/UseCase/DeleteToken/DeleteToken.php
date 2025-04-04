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

namespace Core\Security\Token\Application\UseCase\DeleteToken;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
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
    public function __invoke( PresenterInterface $presenter, string $tokenName, ?int $userId = null): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to delete a token",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::deleteNotAllowed())
                );

                return;
            }

            $userId ??= $this->user->getId();

            if (! ($token = $this->readTokenRepository->findByNameAndUserId($tokenName, $userId))) {
                $this->error(
                    'Token not found',
                    ['token_name' => $tokenName, 'user_id' => $userId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Token'));

                return;
            }

            if (! $this->canUserDeleteToken($this->user, $token)) {
                $this->error(
                    'Not allowed to delete token linked to user who isn\'t the requester',
                    ['token_name' => $tokenName, 'user_id' => $userId, 'requester_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::notAllowedToDeleteTokenForUser($userId))
                );

                return;
            }

            $this->writeTokenRepository->deleteByNameAndUserId($tokenName, $userId);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(TokenException::deleteToken())
            );
            $this->error((string) $ex);
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
