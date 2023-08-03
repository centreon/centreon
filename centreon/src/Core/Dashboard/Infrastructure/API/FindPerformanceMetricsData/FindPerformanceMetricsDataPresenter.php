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

namespace Core\Dashboard\Infrastructure\API\FindPerformanceMetricsData;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataPresenterInterface;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class FindPerformanceMetricsDataPresenter extends AbstractPresenter implements FindPerformanceMetricsDataPresenterInterface
{
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindPerformanceMetricsDataResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'base' => $response->base,
                'metrics' => $response->metricsData,
                'times' => $this->formatTimeStampToISO8601($response->times),
            ]);
        }
    }

    /**
     * Undocumented function
     *
     * @param array $times
     * @return array
     */
    private function formatTimeStampToISO8601(array $times): array
    {
        return array_map(function ($time) {
            return (new \DateTime())->setTimeStamp((int) $time)->format(\DateTime::ATOM);
        }, $times);
    }
}