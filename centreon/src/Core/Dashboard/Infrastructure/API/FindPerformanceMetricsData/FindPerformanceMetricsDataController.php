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
use Centreon\Domain\Log\LoggerTrait;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsData;
use Core\Dashboard\Application\UseCase\FindPerformanceMetricsData\FindPerformanceMetricsDataRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Type as TypeConstraint;
use Symfony\Component\Validator\Validation;

final class FindPerformanceMetricsDataController extends AbstractController
{
    use LoggerTrait;
    private const START_DATE_PARAMETER = 'start';
    private const END_DATE_PARAMETER = 'end';
    private const METRIC_NAMES_PARAMETER = 'metric_names';

    public function __invoke(
        FindPerformanceMetricsData $useCase,
        FindPerformanceMetricsDataPresenter $presenter,
        Request $request
    ): Response {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $findPerformanceMetricsDataRequest = $this->createRequest($request);

        $useCase($presenter, $findPerformanceMetricsDataRequest);

        return $presenter->show();
    }

    /**
     * Retrieves date attribute from http request.
     *
     * @param Request $request
     *
     * @return array{
     *  start: \DateTime,
     *  end: \DateTime,
     *  metricIds: array<int>
     * }
     */
    private function validateAndRetrieveParametersFromRequest(Request $request): array
    {
        $startParameter = $request->query->get(self::START_DATE_PARAMETER);
        $endParameter = $request->query->get(self::END_DATE_PARAMETER);
        /** @var string|null $metricNamesParameter */
        $metricNamesParamenter = $request->query->get(self::METRIC_NAMES_PARAMETER);
        if ($startParameter === null || $endParameter === null || $metricNamesParamenter === null) {
            throw new \InvalidArgumentException('Missing mandatory properties');
        }

        $validator = Validation::createValidator();
        $integerConstraint = new TypeConstraint('string');
        $validationConstraints = [];

        try {
            $start = new \DateTime((string) $startParameter);
            $end = new \DateTime((string) $endParameter);
            $metricNames = \explode(',', \trim($metricNamesParamenter, '[]'));
        } catch ( \Throwable $ex) {
            $this->error('Invalid parameters format', ['trace' => (string) $ex]);

            throw new \InvalidArgumentException('Invalid parameters format');
        }

        if (! is_array($metricNames) || [] === $metricNames)  {
            throw new \InvalidArgumentException('Invalid metric names provided');
        }

        foreach ($metricNames as $metricName) {
            $validationConstraints[] = $validator->validate($metricName, $integerConstraint);
        }

        foreach ($validationConstraints as $validationConstraint) {
            if ($validationConstraint->count() > 0) {
                throw new \InvalidArgumentException('Invalid metric name format');
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'metric_names' => $metricNames,
        ];
    }

    /**
     * Create the Request DTO with the query parameters.
     *
     * @param Request $request
     *
     * @return FindPerformanceMetricsDataRequest
     */
    private function createRequest(Request $request): FindPerformanceMetricsDataRequest
    {
        $parameterFromRequest = $this->validateAndRetrieveParametersFromRequest($request);
        $requestDto = new FindPerformanceMetricsDataRequest(
            $parameterFromRequest['start'],
            $parameterFromRequest['end']
        );

        $requestDto->metricNames = $parameterFromRequest['metric_names'];

        return $requestDto;
    }
}