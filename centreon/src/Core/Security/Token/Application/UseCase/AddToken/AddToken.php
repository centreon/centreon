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

namespace Core\Security\Token\Application\UseCase\AddToken;

use Adaptation\Log\LoggerToken;
use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Application\Common\UseCase\StandardResponseInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\TokenFactory;

final class AddToken
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory,
        private readonly AddTokenValidation $validation,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param AddTokenRequest $request
     */
    public function __invoke(AddTokenRequest $request): ResponseStatusInterface|StandardResponseInterface
    {
        try {
            $tokenString = $this->createToken($request);

            $response = $this->createResponse($tokenString);

            LoggerToken::create()->success(
                event: 'creation',
                userId: $this->user->getId(),
                tokenName: $request->name,
                tokenType: $request->type->name,
            );

            return $response;
        } catch (AssertionFailedException|\ValueError $ex) {
            ExceptionLogger::create()->log($ex, [
                'user_id' => $this->user->getId(),
                'token_name' => $request->name,
                'token_type' => $request->type->name,
            ]);

            LoggerToken::create()->warning(
                event: 'creation',
                reason: 'validation error',
                userId: $this->user->getId(),
                tokenName: $request->name,
                tokenType: $request->type->name,
                exception: $ex
            );

            return new InvalidArgumentResponse($ex);
        } catch (TokenException $ex) {
            ExceptionLogger::create()->log($ex, [
                'user_id' => $this->user->getId(),
                'token_name' => $request->name,
                'token_type' => $request->type->name,
            ]);

            LoggerToken::create()->warning(
                event: 'creation',
                reason: 'conflict error',
                userId: $this->user->getId(),
                tokenName: $request->name,
                tokenType: $request->type->name,
                exception: $ex
            );

            return match ($ex->getCode()) {
                TokenException::CODE_CONFLICT => new ConflictResponse($ex),
                default => new ErrorResponse($ex),
            };
        } catch (\Throwable $ex) {
            ExceptionLogger::create()->log($ex, [
                'user_id' => $this->user->getId(),
                'token_name' => $request->name,
                'token_type' => $request->type->name,
            ]);

            LoggerToken::create()->warning(
                event: 'creation',
                reason: 'unexpected error',
                userId: $this->user->getId(),
                tokenName: $request->name,
                tokenType: $request->type->name,
                exception: $ex
            );

            return new ErrorResponse(TokenException::addToken());
        }
    }

    /**
     * @param AddTokenRequest $request
     *
     * @throws AssertionFailedException
     * @throws TokenException
     * @throws \Throwable
     *
     * @return string
     */
    private function createToken(AddTokenRequest $request): string
    {
        $this->validation->assertIsValidUser($request->userId);
        $this->validation->assertIsValidName($request->name, $request->userId);

        $newToken = TokenFactory::createNew(
            $request->type,
            [
                'name' => $request->name,
                'user_id' => $request->userId,
                'creator_id' => $this->user->getId(),
                'creator_name' => $this->user->getName(),
                'expiration_date' => $request->expirationDate,
                'configuration_provider_id' => $this->providerFactory->create(Provider::LOCAL)->getConfiguration()->getId(),
            ],
        );

        $this->writeTokenRepository->add($newToken);

        return $newToken->getToken();
    }

    /**
     * @param string $tokenString
     *
     * @throws AssertionFailedException
     * @throws TokenException
     * @throws \Throwable
     *
     * @return AddTokenResponse
     */
    private function createResponse(string $tokenString): AddTokenResponse
    {
        if (! ($token = $this->readTokenRepository->find($tokenString))) {
            LoggerToken::create()->warning(
                event: 'creation',
                reason: 'token not retrieved successfully after creation',
                userId: $this->user->getId(),
            );

            throw TokenException::errorWhileRetrievingObject();
        }

        return new AddTokenResponse($token, $tokenString);
    }
}
