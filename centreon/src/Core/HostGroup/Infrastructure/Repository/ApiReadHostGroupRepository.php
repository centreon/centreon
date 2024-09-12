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

namespace Core\HostGroup\Infrastructure\Repository;

use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Infrastructure\Repository\ApiCallIterator;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Common\Infrastructure\Repository\ApiResponseTrait;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostGroupNamesById;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiReadHostGroupRepository implements ReadHostGroupRepositoryInterface
{
    use ApiResponseTrait;
    use ApiRepositoryTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function findNames(array $hostGroupIds): HostGroupNamesById
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findAll(?RequestParametersInterface $requestParameters = null): \Traversable&\Countable
    {
        if ($requestParameters !== null) {
            throw RepositoryException::notYetImplemented();
        }
        $apiEndpoint = $this->router->generate('FindHostGroups');
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
            'headers' => ['X-AUTH-TOKEN: ' . $this->authenticationToken],
        ];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->logger->info('Getting host groups using proxy');
        }

        $debugOptions = $options;
        unset($debugOptions['headers'][0]);

        $this->logger->debug('Connexion configuration', [
            'url' => $apiEndpoint,
            'options' => $debugOptions,
        ]);

        return new ApiCallIterator(
            $this->httpClient,
            $this->url . $apiEndpoint,
            $options,
            $this->maxItemsByRequest,
            HostGroupFactory::createFromApi(...),
            $this->logger
        );
    }

    /**
     * @inheritDoc
     */
    public function findAllByAccessGroupIds(?RequestParametersInterface $requestParameters, array $accessGroupIds): \Traversable&\Countable
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findOne(int $hostGroupId): ?HostGroup
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findOneByAccessGroups(int $hostGroupId, array $accessGroups): ?HostGroup
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existsOne(int $hostGroupId): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existsOneByAccessGroups(int $hostGroupId, array $accessGroups): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $hostGroupIds): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(array $hostGroupIds, array $accessGroups): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function nameAlreadyExists(string $hostGroupName): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByHost(int $hostId): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByHostAndAccessGroups(int $hostId, array $accessGroups): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$hostGroupIds): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToAllHostGroups(array $accessGroupIds): bool
    {
        throw RepositoryException::notYetImplemented();
    }
}
