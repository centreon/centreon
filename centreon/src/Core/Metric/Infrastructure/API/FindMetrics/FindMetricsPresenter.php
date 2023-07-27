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

namespace Core\Metric\Infrastructure\API\FindMetrics;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Metric\Application\UseCase\FindMetrics\FindMetricsPresenterInterface;
use Core\Metric\Application\UseCase\FindMetrics\FindMetricsResponse;

class FindMetricsPresenter extends AbstractPresenter implements FindMetricsPresenterInterface
{
    public function __construct(private RequestParametersInterface $requestParameters)
    {
    }
    
    public function presentResponse(FindMetricsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof FindMetricsResponse) {
            $this->present([
                "count" => $response->count,
                "result" => $response->resourceMetrics,
                "meta" => $this->requestParameters->toArray()
            ]);
        }
    }
}