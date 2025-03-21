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
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Application\Type\NoValue;
use Core\Common\Domain\TrimmedString;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\Token;

final class PartialUpdateToken
{
    use LoggerTrait;

    /**
     * @param ContactInterface $contact
     * @param ReadTokenRepositoryInterface $readRepository
     * @param WriteTokenRepositoryInterface $writeRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly ReadTokenRepositoryInterface $readRepository,
        private readonly WriteTokenRepositoryInterface $writeRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine
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
            if ($this->contactCanExecuteUseCase()) {
                $response = $this->partiallyUpdateToken($requestDto, $tokenName, $userId);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to partially update token",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(TokenException::notAllowedToPartiallyUpdateToken());
            }

            $presenter->present($response);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse(TokenException::errorWhilePartiallyUpdatingToken());
        }

        $presenter->setResponseStatus($response);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW);
    }

    /**
     * @param PartialUpdateTokenRequest $requestDto
     * @param string $tokenName
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function partiallyUpdateToken(
        PartialUpdateTokenRequest $requestDto,
        string $tokenName,
        int $userId
    ): ResponseStatusInterface {
        $token = $this->readRepository->findByNameAndUserId($tokenName, $userId);
        if ($token === null) {
            $this->error('Token not found', ['token_name' => $tokenName, 'user_id' => $userId]);

            return new NotFoundResponse('Token');
        }
        $this->updatePropertiesInTransaction($requestDto, $token);

        return new NoContentResponse();
    }

    /**
     * @param PartialUpdateTokenRequest $requestDto
     * @param Token $token
     *
     * @throws \Throwable
     */
    private function updatePropertiesInTransaction(PartialUpdateTokenRequest $requestDto, Token $token): void
    {
        try {
            $this->dataStorageEngine->startTransaction();
            $this->updateToken($requestDto, $token);
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error('Rollback of \'PartialUpdateToken\' transaction');
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateTokenRequest $requestDto
     * @param Token $token
     *
     * @throws \Throwable
     */
    private function updateToken(PartialUpdateTokenRequest $requestDto, Token $token): void
    {
        $this->info(
            'PartialUpdateToken: update is_revoked',
            [
                'token_name' => $token->getName(),
                'user_id' => $token->getUserId(),
                'is_revoked' => $requestDto->isRevoked,
            ]
        );

        if ($requestDto->isRevoked instanceof NoValue) {
            $this->info('is_revoked property is not provided. Nothing to update');

            return;
        }

        $updatedToken = new Token(
            new TrimmedString($token->getName()),
            $token->getUserId(),
            new TrimmedString($token->getUserName()),
            $token->getCreatorId(),
            new TrimmedString($token->getCreatorName()),
            $token->getCreationDate(),
            $token->getExpirationDate(),
            $requestDto->isRevoked
        );

        $this->writeRepository->update($updatedToken);
    }
}
