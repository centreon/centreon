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
     * @param int $maxItemsByRequest
     * @param \Closure $entityFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly array $options,
        private readonly int $maxItemsByRequest,
        private readonly \Closure $entityFactory,
        private readonly LoggerInterface $logger,
    ) {
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

    public function count(): int
    {
        $this->createCache();

        return $this->nbrElements;
    }

    private function createCache(): void
    {
        if (! $this->isCachedCreated) {
            $fromPage = 1;
            $totalPage = 0;
            do {
                $url = $this->url . sprintf('?limit=%d&page=%d', $this->maxItemsByRequest, $fromPage);
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
                    $temp = ($this->entityFactory)($result);
                    if ($temp instanceof \Traversable) {
                        foreach ($temp as $item) {
                            $this->entitiesCache[] = $item;
                        }
                    } else {
                        $this->entitiesCache[] = ($this->entityFactory)($result);
                    }
                }
                $fromPage++;
            } while ($fromPage <= $totalPage);
            $this->isCachedCreated = true;
        }
    }
}
