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

namespace Core\Dashboard\Infrastructure\API\FindPerformanceMetrics;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\FindPerformanceMetricsPresenterInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\FindPerformanceMetricsResponse;
use Core\Dashboard\Application\UseCase\FindPerformanceMetrics\ResourceMetricDto;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class FindPerformanceMetricsPresenter extends AbstractPresenter implements FindPerformanceMetricsPresenterInterface
{
    public function __construct(private RequestParametersInterface $requestParameters, protected PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindPerformanceMetricsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'result' => array_map(fn(ResourceMetricDto $resourceMetric) => [
                    'id' => $resourceMetric->serviceId,
                    'name' => $resourceMetric->resourceName,
                    'parent_name' => $resourceMetric->parentName,
                    'uuid' => 'h' . $resourceMetric->parentId . '-s' . $resourceMetric->serviceId,
                    'metrics' => $resourceMetric->metrics,
                ],$response->resourceMetrics),
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}