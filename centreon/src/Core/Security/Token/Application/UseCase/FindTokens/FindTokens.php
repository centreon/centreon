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
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Application\Common\UseCase\StandardResponseInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;

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
    ) {
    }

    public function __invoke(): ResponseStatusInterface|StandardResponseInterface
    {
        try {
            $this->info('Find authentication tokens', ['parameters' => $this->requestParameters->getSearch()]);
            if ($this->canDisplayAllTokens()) {
                $tokens = $this->readTokenRepository->findByRequestParameters($this->requestParameters);
            } else {
                $tokens = $this->readTokenRepository->findByIdAndRequestParameters(
                    $this->user->getId(),
                    $this->requestParameters
                );
            }

            return new FindTokensResponse($tokens);
        } catch (RequestParametersTranslatorException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            return new ErrorResponse($ex->getMessage());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            return new ErrorResponse(TokenException::errorWhileSearching($ex));
        }
    }

    private function canDisplayAllTokens(): bool
    {
        return $this->user->isAdmin() || $this->user->hasRole(Contact::ROLE_MANAGE_TOKENS);
    }
}
