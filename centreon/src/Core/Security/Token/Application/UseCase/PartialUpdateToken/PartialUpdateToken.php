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

namespace Core\Security\Token\Application\UseCase\PartialUpdateToken;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\Type\NoValue;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\ApiToken;
use Core\Security\Token\Domain\Model\JwtToken;
use Core\Security\Token\Domain\Model\Token;

final class PartialUpdateToken
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param ReadTokenRepositoryInterface $readRepository
     * @param WriteTokenRepositoryInterface $writeRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadTokenRepositoryInterface $readRepository,
        private readonly WriteTokenRepositoryInterface $writeRepository,
    ) {
    }

    /**
     * @param PartialUpdateTokenRequest $requestDto
     * @param PresenterInterface $presenter
     * @param string $tokenName
     * @param int $userId
     */
    public function __invoke(
        PartialUpdateTokenRequest $requestDto,
        PresenterInterface $presenter,
        string $tokenName,
        int $userId
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)) {
                $this->error(
                    'User is not allowed to partially update token',
                    ['token_name' => $tokenName, 'user_id' => $userId, 'requester_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::notAllowedToPartiallyUpdateToken())
                );

                return;
            }

            $token = $this->readRepository->findByNameAndUserId($tokenName, $userId);
            if ($token === null) {
                $this->error(
                    'Token not found',
                    ['token_name' => $tokenName, 'user_id' => $userId, 'requester_id' => $this->user->getId()]
                );

                $presenter->setResponseStatus(new NotFoundResponse('Token'));

                return;
            }

            if (! $this->canUserUpdateToken($token)) {
                $this->error(
                    'User is not allowed to partially update token',
                    ['token_name' => $tokenName, 'user_id' => $userId, 'requester_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(TokenException::notAllowedToPartiallyUpdateToken())
                );

                return;
            }

            $this->updateToken($requestDto, $token);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new ErrorResponse(TokenException::errorWhilePartiallyUpdatingToken())
            );
        }
    }

    private function canUserUpdateToken(Token $token): bool
    {
        return (bool) (
            $this->user->isAdmin()
            || $this->user->hasRole(Contact::ROLE_MANAGE_TOKENS)
            || ($token instanceof ApiToken && $token->getUserId() === $this->user->getId())
            || ($token instanceof JwtToken && $token->getCreatorId() === $this->user->getId())
        );
    }

    /**
     * @param PartialUpdateTokenRequest $requestDto
     * @param Token $token
     *
     * @throws \Throwable
     */
    private function updateToken(PartialUpdateTokenRequest $requestDto, Token $token): void
    {
        if ($requestDto->isRevoked instanceof NoValue) {
            $this->debug('is_revoked property is not provided. Nothing to update');

            return;
        }

        $token->setIsRevoked($requestDto->isRevoked);

        $this->writeRepository->update($token);
    }
}
