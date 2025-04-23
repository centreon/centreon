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

namespace CentreonOpenTickets\Providers\Application\UseCase;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use CentreonOpenTickets\Providers\Application\Exception\ProviderException;
use CentreonOpenTickets\Providers\Application\Repository\ReadProviderRepositoryInterface;
use CentreonOpenTickets\Providers\Domain\Model\Provider;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;

final class FindProviders
{
    use LoggerTrait;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param ReadProviderRepositoryInterface $repository
     */
    public function __construct(
        private RequestParametersInterface $requestParameters,
        private ReadProviderRepositoryInterface $repository
    ) {
    }

    /**
     * @return FindProvidersResponse|ResponseStatusInterface
     */
    public function __invoke(): FindProvidersResponse|ResponseStatusInterface
    {
        try {
            $providers = $this->repository->findAll($this->requestParameters);

            return $this->createResponse($providers);
        } catch (RepositoryException $exception) {
            $this->error(
                $exception->getMessage(),
                [
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ],
                ],
            );

            return new ErrorResponse(ProviderException::errorWhileListingProviders());
        }
    }

    /**
     * @param Provider[] $providers
     *
     * @return FindProvidersResponse
     */
    private function createResponse(array $providers): FindProvidersResponse
    {
        $response = new FindProvidersResponse();
        $response->providers = array_map(
            static fn (Provider $provider): ProviderDto => new ProviderDto(
                id: $provider->getId(),
                name: $provider->getName(),
                type: $provider->getType(),
                isActivated: $provider->isActivated()
            ),
            $providers
        );

        return $response;
    }
}
