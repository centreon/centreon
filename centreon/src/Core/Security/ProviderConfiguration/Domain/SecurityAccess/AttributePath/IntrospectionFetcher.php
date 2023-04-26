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
use Symfony\Component\HttpFoundation\Response;

class IntrospectionFetcher implements AttributePathFetcherInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly LoginLoggerInterface $loginLogger,
        private readonly ReadAttributePathRepositoryInterface $attributePathRepository
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
        $customConfiguration = $configuration->getCustomConfiguration();
        $url = $customConfiguration->getBaseUrl() . '/'
            . ltrim($customConfiguration->getIntrospectionTokenEndpoint(), '/');

        try {
            return $this->attributePathRepository->getData($url, $accessToken, $configuration);
        } catch (InvalidResponseException $invalidResponseException) {
            $this->loginLogger->exception(
                $scope,
                "Unable to get Introspection Information: %s, message: %s",
                $invalidResponseException
            );
            $this->error(sprintf(
                "[Error] Unable to get Introspection Token Information:, message: %s",
                $invalidResponseException->getMessage()
            ));
            throw SSOAuthenticationException::requestForIntrospectionTokenFail();
        } catch (InvalidContentException $invalidContentException) {
            $content = $invalidContentException->getContent();

            $this->loginLogger->error($scope, 'Introspection Token Info: ', $content);
            $this->error(
                'error from external provider :' . (array_key_exists('error', $content)
                    ? $content['error']
                    : 'No content in response')
            );
            throw SSOAuthenticationException::errorFromExternalProvider($configuration->getName());
        } catch (InvalidStatusCodeException $invalidStatusCodeException) {
            $this->loginLogger->exception(
                $scope,
                "Unable to get Introspection Information: %s, message: %s",
                SSOAuthenticationException::requestForIntrospectionTokenFail()
            );
            $this->error(
                sprintf(
                    "invalid status code return by external provider, [%d] returned, [%d] expected",
                    $invalidStatusCodeException->getCode(),
                    200
                )
            );
            throw SSOAuthenticationException::requestForIntrospectionTokenFail();
        }
    }

    /**
     * @inheritDoc
     */
    public function supports(Endpoint $endpoint): bool
    {
        return $endpoint->getType() === Endpoint::INTROSPECTION;
    }
}
