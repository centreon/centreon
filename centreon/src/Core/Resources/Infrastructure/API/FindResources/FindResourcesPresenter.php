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

namespace Core\Resources\Infrastructure\API\FindResources;

use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Resources\Application\UseCase\FindResources\FindResourcesResponse;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Resources\Application\UseCase\FindResources\FindResourcesPresenterInterface;

final class FindResourcesPresenter extends DefaultPresenter implements FindResourcesPresenterInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindResourcesResponse|ResponseStatusInterface $data
     */
    public function presentResponse(FindResourcesResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof FindResourcesResponse) {
            $result = [];

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }
}
