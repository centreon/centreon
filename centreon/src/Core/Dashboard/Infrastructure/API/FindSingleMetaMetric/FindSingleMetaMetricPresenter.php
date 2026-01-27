<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\API\FindSingleMetaMetric;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Dashboard\Application\UseCase\FindSingleMetaMetric\FindSingleMetaMetricPresenterInterface;
use Core\Dashboard\Application\UseCase\FindSingleMetaMetric\FindSingleMetaMetricResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

final class FindSingleMetaMetricPresenter extends AbstractPresenter implements FindSingleMetaMetricPresenterInterface
{
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter,
        private readonly ExceptionLogger $exceptionLogger,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindSingleMetaMetricResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            if (($response instanceof ErrorResponse) && ! is_null($response->getException())) {
                $this->exceptionLogger->log($response->getException());
            }
            $this->setResponseStatus($response);

            return;
        }

        $this->present([
            'metrics' => [
                [
                    'metric_id' => $response->id,
                    'name' => $response->name,
                    'unit' => $response->unit,
                    'current_value' => $response->currentValue,
                    'warning_high_threshold' => $response->warningHighThreshold,
                    'warning_low_threshold' => $response->warningLowThreshold,
                    'critical_high_threshold' => $response->criticalHighThreshold,
                    'critical_low_threshold' => $response->criticalLowThreshold,
                ],
            ],
        ]);
    }
}
