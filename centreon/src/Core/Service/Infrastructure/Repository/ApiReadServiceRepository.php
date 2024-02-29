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

namespace Core\Service\Infrastructure\Repository;

use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Infrastructure\Repository\ApiCallIterator;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Common\Infrastructure\Repository\ApiResponseTrait;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\Service\Domain\Model\TinyService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiReadServiceRepository implements ReadServiceRepositoryInterface
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
    public function exists(int $serviceId): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $serviceId, array $accessGroups): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findMonitoringServerId(int $serviceId): int
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findServiceIdsLinkedToHostId(int $hostId): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findServiceNamesByHost(int $hostId): ?ServiceNamesByHost
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $serviceId): ?Service
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByIds(int ...$serviceIds): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findAll(): \Traversable&\Countable
    {
        $apiEndpoint = $this->router->generate('FindServices');
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
            'headers' => ['X-AUTH-TOKEN: ' . $this->authenticationToken],
        ];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->logger->info('Getting services using proxy');
        }
        $debugOptions = $options;
        unset($debugOptions['headers'][0]);

        $this->logger->debug('Connexion configuration', [
            'url' => $apiEndpoint,
            'options' => $debugOptions,
        ]);

        /**
         * @param array{id: int, name: string, hosts: array<array{name: string}>} $data
         *
         * @return \Generator<TinyService>
         */
        $serviceFactory = function (array $data): \Generator {
            $propertyName = isset($data['hosts']) ? 'hosts' : 'host';
            foreach ($data[$propertyName] as $host) {
                yield TinyServiceFactory::createFromApi([
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'host_name' => $host['name'],
                ]);
            }
        };

        return new ApiCallIterator(
            $this->httpClient,
            $this->url . $apiEndpoint,
            $options,
            $this->maxItemsByRequest,
            $serviceFactory,
            $this->logger
        );
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $serviceId): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameterAndAccessGroup(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
    ): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $serviceIds): array
    {
        throw RepositoryException::notYetImplemented();
    }
}
