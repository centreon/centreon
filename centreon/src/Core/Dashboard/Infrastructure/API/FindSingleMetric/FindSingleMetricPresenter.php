<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\API\FindSingleMetric;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetricPresenterInterface;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetricResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

final class FindSingleMetricPresenter extends AbstractPresenter implements FindSingleMetricPresenterInterface
{
    public function __construct(protected PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindSingleMetricResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);

            return;
        }

        $metric = $response->metricDto;
        $this->present([
            'id' => $metric->id,
            'name' => $metric->name,
            'unit' => $metric->unit,
            'current_value' => $metric->currentValue,
            'warning_high_threshold' => $metric->warningHighThreshold,
            'warning_low_threshold' => $metric->warningLowThreshold,
            'critical_high_threshold' => $metric->criticalHighThreshold,
            'critical_low_threshold' => $metric->criticalLowThreshold,
        ]);
    }
}
