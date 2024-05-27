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

namespace Core\HostCategory\Infrastructure\API\FindRealTimeHostCategories;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostCategory\Application\UseCase\FindRealTimeHostCategories\FindRealTimeHostCategoriesPresenterInterface;
use Core\HostCategory\Application\UseCase\FindRealTimeHostCategories\FindRealTimeHostCategoriesResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class FindRealTimeHostCategoriesPresenter extends AbstractPresenter implements FindRealTimeHostCategoriesPresenterInterface
{
    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(
        private RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
    }

    public function presentResponse(FindRealTimeHostCategoriesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            parent::present([
                'result' => $response->tags,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
