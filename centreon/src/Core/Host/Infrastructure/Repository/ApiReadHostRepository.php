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

namespace Core\Host\Infrastructure\Repository;

use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Common\Infrastructure\Repository\ApiResponseTrait;
use Core\Common\Infrastructure\Repository\ValuesByPackets;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostNamesById;
use Core\Host\Domain\Model\TinyHost;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiReadHostRepository implements ReadHostRepositoryInterface
{
    use ApiResponseTrait;
    use ApiRepositoryTrait;
    public const URL_SAFETY_MARGIN = 50;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function existsByName(string $hostName): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findById(int $hostId): ?Host
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByIds(array $hostIds): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByNames(array $hostNames): array
    {
        $apiEndpoint = $this->router->generate('FindHosts');
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
            'headers' => ['X-AUTH-TOKEN: ' . $this->authenticationToken],
        ];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->logger->info('Getting hosts using proxy');
        }

        $debugOptions = $options;
        unset($debugOptions['headers'][0]);

        $this->logger->debug('Connexion configuration', [
            'url' => $apiEndpoint,
            'options' => $debugOptions,
        ]);

        $url = $this->url . $apiEndpoint;

        /**
         * @param list<string> $hostNames
         *
         * @throws TransportExceptionInterface
         * @throws ClientExceptionInterface
         * @throws RedirectionExceptionInterface
         * @throws ServerExceptionInterface
         *
         * @return \Generator<TinyHost>
         */
        $findHosts = function (array $hostNames) use ($url, $options): \Generator {
            // Surround hostnames with double quotes
            $hostNames = array_map(fn(string $name) => sprintf('"%s"', $name), $hostNames);

            $options['query'] = [
                'limit' => count($hostNames),
                'search' => sprintf('{"name": {"$in": [%s]}}', implode(',', $hostNames)),
            ];
            $this->logger->debug('Call API', ['url' => $url, 'query_parameter' => $options['query']['search']]);

            /**
             * @var array{
             *     result: array<array{id: int, name: string, alias: string|null, monitoring_server: array{id: int}}>
             * } $response
             */
            $response = $this->getResponseOrFail($this->httpClient->request('GET', $url, $options));
            foreach ($response['result'] as $result) {
                yield (TinyHostFactory::createFromApi(...))($result);
            }
        };

        $hosts = [];
        $maxQueryStringLength = $this->maxQueryStringLength - mb_strlen($url) - self::URL_SAFETY_MARGIN;
        foreach (new ValuesByPackets($hostNames, $this->maxItemsByRequest, $maxQueryStringLength) as $names) {
            foreach ($findHosts($names) as $host) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findParents(int $hostId): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function exists(int $hostId): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $hostId, array $accessGroups): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findNames(array $hostGroupIds): HostNamesById
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $hostIds): array
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        throw RepositoryException::notYetImplemented();
    }
}
