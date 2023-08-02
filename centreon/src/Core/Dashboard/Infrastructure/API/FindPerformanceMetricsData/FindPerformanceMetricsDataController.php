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

use Centreon\Application\Controller\AbstractController;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsData;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FindPerformanceMetricsDataController extends AbstractController
{
    private const START_DATE_PARAMETER="start";
    private const END_DATE_PARAMETER="end";

    public function __construct(private readonly Request $request)
    {
    }

    public function __invoke(
        FindPerformanceMetricsData $useCase,
        FindPerformanceMetricsDataPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $request = $this->createRequest();

        $useCase($presenter, $request);

        return $presenter->show();
    }

    /**
     * Retrieves date attribute from http request.
     *
     * @return \DateTimeImmutable[]
     */
    private function findDatesInRequest(): array
    {
        $startParameter = $this->request->query->get(self::START_DATE_PARAMETER);
        $endParameter = $this->request->query->get(self::END_DATE_PARAMETER);
        $metricIds = $this->request->query->get('metricIds');
        if ($startParameter !== null && $endParameter !== null) {
            return [
                'start' => new \DateTimeImmutable((string) $startParameter),
                'end' => new \DateTimeImmutable((string) $endParameter),
            ];
        }

        return [];
    }

    /**
     * Create the Request DTO with the query parameters.
     *
     * @return FindPerformanceMetricsDataRequest
     */
    private function createRequest(): FindPerformanceMetricsDataRequest
    {
        $request = new FindPerformanceMetricsDataRequest();
        $dateFromRequest = $this->findDatesInRequest();
        if (! empty($dateFromRequest)) {
            $request->startDate = $dateFromRequest['start'];
            $request->endDate = $dateFromRequest['end'];
        }

        return $request;
    }
}