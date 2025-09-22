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

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetric;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetricRequest;
use Core\Security\Infrastructure\Voters\ApiRealtimeVoter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    path: '/monitoring/hosts/{hostId}/services/{serviceId}/metrics/{metricName}',
    name: 'FindSingleMetric',
    methods: ['GET'],
    requirements: [
        'hostId' => '\d+',
        'serviceId' => '\d+',
        'metricName' => '.+',
    ],
    condition: "request.attributes.get('version') >= 25.07"
)]
#[IsGranted(
    ApiRealtimeVoter::ROLE_API_REALTIME,
    null,
    'You are not allowed to retrieve metrics in real-time',
    Response::HTTP_FORBIDDEN
)]
final class FindSingleMetricController extends AbstractController
{
    /**
     * @param int $hostId
     * @param int $serviceId
     * @param string $metricName
     * @param FindSingleMetric $useCase
     * @param FindSingleMetricPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        int $hostId,
        int $serviceId,
        string $metricName,
        FindSingleMetric $useCase,
        FindSingleMetricPresenter $presenter,
    ): Response {
        try {
            $request = new FindSingleMetricRequest(
                hostId: $hostId,
                serviceId: $serviceId,
                metricName: $metricName
            );
        } catch (\InvalidArgumentException $e) {
            $presenter->presentResponse(new InvalidArgumentResponse(
                'Invalid parameters provided : ' . $e->getMessage(),
                [
                    'host_id' => $hostId,
                    'service_id' => $serviceId,
                    'metric_name' => $metricName,
                ]
            ));

            return $presenter->show();
        }

        $useCase($request, $presenter);

        return $presenter->show();
    }
}
