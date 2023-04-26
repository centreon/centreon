<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *  For more information : contact@centreon.com
 */

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath;

use Core\Security\ProviderConfiguration\Domain\Exception\AttributePathFetcherNotFoundException;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;

class AttributePathFetcher
{
    /**
     * @var AttributePathFetcherInterface[]
     */
    private array $fetchers;

    /**
     * @param IntrospectionFetcher $introspectionFetcher
     * @param UserInformationFetcher $userInformationFetcher
     * @param HttpUrlFetcher $httpUrlFetcher
     */
    public function __construct(
        private readonly IntrospectionFetcher $introspectionFetcher,
        private readonly UserInformationFetcher $userInformationFetcher,
        private readonly HttpUrlFetcher $httpUrlFetcher,
    ) {
        $this->fetchers = [
            $this->introspectionFetcher,
            $this->userInformationFetcher,
            $this->httpUrlFetcher
        ];
    }

    /**
     * @param string $accessToken
     * @param Configuration $configuration
     * @param Endpoint $endpoint
     * @return array
     */
    public function fetch(string $accessToken, Configuration $configuration, Endpoint $endpoint): array
    {
        return $this->findFirstFetcher($endpoint)->fetch($accessToken, $configuration, $endpoint);
    }

    /**
     * @param Endpoint $endpoint
     * @return AttributePathFetcherInterface
     */
    private function findFirstFetcher(Endpoint $endpoint): AttributePathFetcherInterface
    {
        foreach ($this->fetchers as $fetcher) {
            if ($fetcher->supports($endpoint)) {
                return $fetcher;
            }
        }

        throw AttributePathFetcherNotFoundException::create($endpoint->getType());
    }
}
