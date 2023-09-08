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

namespace Core\Security\Token\Application\UseCase\AddToken;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\NewToken;
use Security\Encryption;

final class AddToken
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ProviderAuthenticationFactoryInterface $providerFactory,
        private readonly AddTokenValidation $validation,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param AddTokenRequest $request
     * @param AddTokenPresenterInterface $presenter
     */
    public function __invoke(AddTokenRequest $request, AddTokenPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to add a token",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(TokenException::addNotAllowed()->getMessage())
                );

                return;
            }

            $tokenString = $this->createToken($request);

            $presenter->presentResponse(
                $this->createResponse($tokenString)
            );
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (TokenException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    TokenException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(TokenException::addToken())
            );
            $this->error((string) $ex);
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
        $this->validation->assertIsValidName($request->name);
        $this->validation->assertIsValidUser($request->userId);

        $localProviderId = $this->providerFactory->create(Provider::LOCAL)->getConfiguration()->getId();
        $tokenString = Encryption::generateRandomString();
        $creationDate = new \DateTimeImmutable();
        $expirationDate = new \DateTimeImmutable($request->expirationDate->format('Y-m-d'));

        $this->validation->assertIsValidExpirationDate($expirationDate, $creationDate);

        $newToken = new NewToken(
            token: $tokenString,
            creationDate: $creationDate,
            expirationDate: $expirationDate,
            userId: $request->userId,
            configurationProviderId: $localProviderId,
            name: $request->name,
            creatorId: $this->user->getId(),
            creatorName: $this->user->getName(),
        );

        $this->writeTokenRepository->add($newToken);

        return $tokenString;
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
        if (! ($apiToken = $this->readTokenRepository->find($tokenString))) {
            throw TokenException::errorWhileRetrievingObject();
        }
        $userNames = $this->readContactRepository->findNamesByIds($apiToken->getUserId());

        $responseDto = new AddTokenResponse();
        $responseDto->name = $apiToken->getName();
        $responseDto->userId = $apiToken->getUserId();
        $responseDto->userName = $userNames[$apiToken->getUserId()]['name'];
        $responseDto->creatorId = $apiToken->getCreatorId();
        $responseDto->creatorName = $apiToken->getCreatorName();
        $responseDto->creationDate = $apiToken->getCreationDate();
        $responseDto->expirationDate = $apiToken->getExpirationDate();
        $responseDto->token = $apiToken->getToken();
        $responseDto->isRevoked = $apiToken->isRevoked();

        return $responseDto;
    }
}
