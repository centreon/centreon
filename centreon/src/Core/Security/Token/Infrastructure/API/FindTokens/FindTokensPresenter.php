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

namespace Core\Security\Token\Infrastructure\API\FindTokens;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokensPresenterInterface;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokensResponse;

final class FindTokensPresenter extends AbstractPresenter implements FindTokensPresenterInterface
{
    use PresenterTrait;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param ResponseStatusInterface|FindTokensResponse $response
     */
    public function presentResponse(ResponseStatusInterface|FindTokensResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $tokens = [];
            foreach ($response->tokens as $token) {
                $tokens[] = [
                    'name' => $token->name,
                    'user' => [
                        'id' => $token->userId,
                        'name' => $token->userName,
                    ],
                    'creator' => [
                        'id' => $token->creatorId,
                        'name' => $token->creatorName,
                    ],
                    'creation_date' => $this->formatDateToIso8601($token->creationDate),
                    'expiration_date' => $this->formatDateToIso8601($token->expirationDate),
                    'is_revoked' => $token->isRevoked,
                ];
            }
            $this->present([
                'result' => $tokens,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
