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

namespace Core\Service\Infrastructure\API\FindRealTimeUniqueServiceNames;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames\FindRealTimeUniqueServiceNamesPresenterInterface;
use Core\Service\Application\UseCase\FindRealTimeUniqueServiceNames\FindRealTimeUniqueServiceNamesResponse;

final class FindRealTimeUniqueServiceNamesPresenter extends AbstractPresenter implements FindRealTimeUniqueServiceNamesPresenterInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(FindRealTimeUniqueServiceNamesResponse|ResponseStatusInterface $response): void
    {
        $result = [];

        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = array_map(
                static fn (string $name): array => ['name' => $name],
                $response->names
            );
        }

        $this->present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
