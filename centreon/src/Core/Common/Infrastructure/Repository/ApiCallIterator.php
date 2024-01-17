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

namespace Core\Common\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Repository\RepositoryException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @template T
 *
 * @implements \IteratorAggregate<int, T>&\Countable
 */
class ApiCallIterator implements \IteratorAggregate, \Countable
{
    use ApiResponseTrait;

    private int $nbrElements = 0;

    /** @var list<T> */
    private array $entitiesCache = [];

    private bool $isCachedCreated = false;

    /**
     * @param HttpClientInterface $httpClient
     * @param string $url
     * @param array<string, mixed> $options
     * @param positive-int $maxItemsByRequest
     * @param \Closure $entityFactory
     * @param LoggerInterface $logger
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly array $options,
        private readonly int $maxItemsByRequest,
        private readonly \Closure $entityFactory,
        private readonly LoggerInterface $logger,
    ) {
       Assertion::positiveInt($this->maxItemsByRequest, 'maxItemsByRequest');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getIterator(): \Traversable
    {
        $this->createCache();
        foreach ($this->entitiesCache as $entity) {
            yield $entity;
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function count(): int
    {
        $this->createCache();

        return $this->nbrElements;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RepositoryException
     */
    private function createCache(): void
    {
        if (! $this->isCachedCreated) {
            $fromPage = 1;
            $totalPage = 0;
            do {
                $separator = parse_url($this->url, PHP_URL_QUERY) ? '&' : '?';
                $url = $this->url . $separator . sprintf('limit=%d&page=%d', $this->maxItemsByRequest, $fromPage);
                $this->logger->debug('Call API', ['url' => $this->url]);
                /** @var array{meta: array{total: int}, result: array<string, mixed>} $response */
                $response = $this->getResponseOrFail($this->httpClient->request('GET', $url, $this->options));
                if ($this->nbrElements === 0) {
                    $this->nbrElements = (int) $response['meta']['total'];
                    $totalPage = (int) ceil($this->nbrElements / $this->maxItemsByRequest);
                    $this->logger->debug(
                        'First API call status', ['nbr_elements' => $this->nbrElements, 'nbr_page' => $totalPage]
                    );
                }
                foreach ($response['result'] as $result) {
                    $entity = ($this->entityFactory)($result);
                    if ($entity instanceof \Traversable) {
                        foreach ($entity as $oneEntity) {
                            $this->entitiesCache[] = $oneEntity;
                        }
                    } else {
                        $this->entitiesCache[] = $entity;
                    }
                }
                $fromPage++;
            } while ($fromPage <= $totalPage);
            $this->isCachedCreated = true;
        }
    }
}
