<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
 * For more information : contact@centreon.com
 *
 */
declare(strict_types=1);

namespace Core\Resources\Infrastructure\API\CountResources;

use Centreon\Domain\RequestParameters\RequestParameters;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Domain\Exception\InternalErrorException;
use Core\Common\Infrastructure\ExceptionHandler;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Resources\Application\UseCase\CountResources\CountResourcesPresenterInterface;
use Core\Resources\Application\UseCase\CountResources\CountResourcesResponse;

/**
 * Class
 *
 * @class CountResourcesPresenterJson
 * @package Core\Resources\Infrastructure\API\CountResources
 */
class CountResourcesPresenterJson extends AbstractPresenter implements CountResourcesPresenterInterface {
    /**
     * CountResourcesPresenterJson constructor
     *
     * @param PresenterFormatterInterface $presenterFormatter
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
        private readonly ExceptionHandler $exceptionHandler
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param CountResourcesResponse|ResponseStatusInterface $response
     *
     * @return void
     */
    public function presentResponse(CountResourcesResponse|ResponseStatusInterface $response): void
    {

        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                $this->exceptionHandler->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        try {
            $search = $this->formatSearchParameter($input->search ?? '');

            $this->present(
                [
                    'count' => $response->getTotalFilteredResources(),
                    'meta' => [
                        'search' => $search,
                        'total' => $response->getTotalResources(),
                    ],
                ]
            );
        } catch (InternalErrorException $exception) {
            $this->exceptionHandler->log($exception);
            $this->setResponseStatus(new ErrorResponse($exception->getMessage()));
        }
    }

    /**
     * @param string $search
     *
     * @throws InternalErrorException
     * @return array<string,mixed>
     */
    private function formatSearchParameter(string $search): array
    {
        try {
            $requestParameters = new RequestParameters();
            $requestParameters->setSearch($search);

            return (array) $requestParameters->toArray()['search'];
        } catch (\Throwable $exception) {
            throw new InternalErrorException(
                'Error while formatting search parameter',
                ['search' => $search],
                $exception
            );
        }
    }
}
