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

namespace Core\Metric\Infrastructure\API\FindMetricsByService;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Metric\Application\UseCase\FindMetricsByService\FindMetricsByServicePresenterInterface;
use Core\Metric\Application\UseCase\FindMetricsByService\FindMetricsByServiceResponse;
use Core\Metric\Application\UseCase\FindMetricsByService\MetricDto;

final class FindMetricsByServicePresenter extends AbstractPresenter implements FindMetricsByServicePresenterInterface
{
    public function __construct(protected PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindMetricsByServiceResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(array_map(fn(MetricDto $metric) => [
                'id' => $metric->id,
                'name' => $metric->name,
                'unit' => $metric->unit,
                'current_value' => $metric->currentValue,
                'warning_high_threshold' => $metric->warningHighThreshold,
                'warning_low_threshold' => $metric->warningLowThreshold,
                'critical_high_threshold' => $metric->criticalHighThreshold,
                'critical_low_threshold' => $metric->criticalLowThreshold,
            ], $response->metricsDto));
        }
    }
}