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

declare(strict_types = 1);

namespace Core\Security\Token\Application\UseCase\FindTokens;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\Token;

final class FindTokens
{
    use LoggerTrait;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param ReadTokenRepositoryInterface $readTokenRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ContactInterface $user,
    )
    {
    }

    /**
     * @param FindTokensPresenterInterface $presenter
     */
    public function __invoke(FindTokensPresenterInterface $presenter): void
    {
        try {
            if (! $this->canDisplayTokens()) {
                $this->error(
                    "User doesn't have sufficient rights to list tokens",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(new ForbiddenResponse(TokenException::notAllowedToListTokens()));

                return;
            }

            $this->info('Find authentication tokens', ['parameters' => $this->requestParameters->getSearch()]);
            if ($this->canDisplayAllTokens()) {
                $tokens = $this->readTokenRepository->findByRequestParameters($this->requestParameters);
            } else {
                $tokens = $this->readTokenRepository->findByIdAndRequestParameters(
                    $this->user->getId(),
                    $this->requestParameters
                );
            }
            $presenter->presentResponse($this->createResponse($tokens));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(
                new ErrorResponse($ex->getMessage())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(TokenException::errorWhileSearching($ex))
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Token[] $tokens
     */
    private function createResponse(array $tokens): FindTokensResponse
    {
        $response = new FindTokensResponse();
        foreach ($tokens as $token) {
            $response->tokens[] = new TokenDto(
                $token->getName(),
                $token->getUserId(),
                $token->getUserName(),
                $token->getCreatorId(),
                $token->getCreatorName(),
                $token->getCreationDate(),
                $token->getExpirationDate(),
                $token->isRevoked(),
            );
        }

        return $response;
    }

    private function canDisplayTokens(): bool
    {
        return $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW);
    }

    private function canDisplayAllTokens(): bool
    {
        return $this->canDisplayTokens()
            && ($this->user->isAdmin() || $this->user->hasRole(Contact::ROLE_MANAGE_TOKENS));
    }
}
