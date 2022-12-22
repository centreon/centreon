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

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidContentException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidResponseException;
use Core\Security\ProviderConfiguration\Domain\Exception\Http\InvalidStatusCodeException;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Repository\ReadAttributePathRepositoryInterface;

class HttpUrlFetcher implements AttributePathFetcherInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAttributePathRepositoryInterface $attributePathRepository,
        private readonly LoginLoggerInterface $loginLogger
    ) {
    }

    /**
     * @param string $accessToken
     * @param Configuration $configuration
     * @param Endpoint $endpoint
     * @return array<string,mixed>
     * @throws SSOAuthenticationException
     */
    public function fetch(string $accessToken, Configuration $configuration, Endpoint $endpoint): array
    {
        $scope = $configuration->getType();
        $customEndpoint = $endpoint->getUrl();
        $customConfiguration = $configuration->getCustomConfiguration();
        $url = str_starts_with($customEndpoint, '/')
            ? $customConfiguration->getBaseUrl() . $customEndpoint
            : $customEndpoint;

        try {
            return $this->attributePathRepository->getData($url, $accessToken, $configuration);
        } catch (InvalidResponseException) {
            throw SSOAuthenticationException::requestOnCustomEndpointFailed();
        } catch (InvalidStatusCodeException $invalidStatusCodeException) {
            $this->loginLogger->exception(
                $scope,
                "Unable to get custom endpoint information: %s, message: %s",
                SSOAuthenticationException::requestOnCustomEndpointFailed()
            );

            $this->error(
                sprintf(
                    "invalid status code return by external provider, [%d] returned, [%d] expected",
                    $invalidStatusCodeException->getCode(),
                    200
                )
            );

            throw SSOAuthenticationException::requestOnCustomEndpointFailed();
        } catch (InvalidContentException $invalidContentException) {
            $content = $invalidContentException->getContent();
            $this->loginLogger->error($scope, 'Custom endpoint Info: ', $content);
            $this->error(
                'error from external provider :' . (array_key_exists('error', $content)
                    ? $content['error']
                    : 'No content in response')
            );

            throw SSOAuthenticationException::errorFromExternalProvider($configuration->getName());
        }
    }

    /**
     * @inheritDoc
     */
    public function supports(Endpoint $endpoint): bool
    {
        return $endpoint->getType() === Endpoint::CUSTOM;
    }
}
