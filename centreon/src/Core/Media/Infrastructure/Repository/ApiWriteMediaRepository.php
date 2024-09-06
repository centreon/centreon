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

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Common\Infrastructure\Repository\ApiResponseTrait;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Media\Domain\Model\NewMedia;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiWriteMediaRepository implements WriteMediaRepositoryInterface
{
    use LoggerTrait;
    use ApiRepositoryTrait;
    use ApiResponseTrait;

    public function __construct(readonly private HttpClientInterface $httpClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function delete(Media $media): void
    {
        throw RepositoryException::notYetImplemented();
    }

    /**
     * @inheritDoc
     */
    public function add(NewMedia $media): int
    {
        $apiEndpoint = $this->url . '/api/latest/configuration/medias';
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
        ];

        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->info('Adding media using proxy');
        }
        $this->debug('Connexion configuration', [
            'url' => $apiEndpoint,
            'options' => $options,
        ]);
        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            throw new \Exception('Impossible to create resource on php://memory');
        }
        fwrite($stream, $media->getData());
        rewind($stream);
        $formFields = [
            'data' => new DataPart($stream, $media->getFilename(), 'multipart/form-data'),
            'directory' => $media->getDirectory(),
        ];
        $formData = new FormDataPart($formFields);
        $options['headers'] = $formData->getPreparedHeaders()->toArray();
        $options['headers'][] = 'X-AUTH-TOKEN: ' . $this->authenticationToken;
        $options['body'] = $formData->bodyToString();

        /** @var array{result: array<int, array{id: int}>, errors: array<int, array{reason: string}>} $response */
        $response = $this->getResponseOrFail($this->httpClient->request('POST', $apiEndpoint, $options));

        if (isset($response['errors'][0]['reason'])) {
            throw new \Exception($response['errors'][0]['reason']);
        }

        return (int) $response['result'][0]['id'];
    }
}
