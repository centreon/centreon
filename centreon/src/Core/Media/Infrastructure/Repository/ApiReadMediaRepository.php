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

namespace Core\Media\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Infrastructure\Repository\ApiCallIterator;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiReadMediaRepository implements ReadMediaRepositoryInterface
{
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
    public function findById(int $mediaId): ?Media
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function existsByPath(string $path): bool
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function findAll(): \Traversable&\Countable
    {
        $apiEndpoint = $this->router->generate('FindMedias');
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
            'headers' => ['X-AUTH-TOKEN: ' . $this->authenticationToken],
        ];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->logger->info('Getting media using proxy');
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
            $this->createMedia(...),
            $this->logger
        );
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): \Traversable
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @param array{id: int, filename: string, directory: string} $data
     *
     * @throws AssertionFailedException
     *
     * @return Media
     */
    private function createMedia(array $data): Media
    {
        return new Media(
            $data['id'],
            (string) $data['filename'],
            (string) $data['directory'],
            null,
            null
        );
    }
}
