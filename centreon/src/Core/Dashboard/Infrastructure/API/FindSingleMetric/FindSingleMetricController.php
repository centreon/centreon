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
use Core\Application\Common\UseCase\ValidationErrorResponse;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetric;
use Core\Dashboard\Application\UseCase\FindSingleMetric\FindSingleMetricRequest;
use Core\Security\Infrastructure\Voters\ApiRealtimeVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted(
    ApiRealtimeVoter::ROLE_API_REALTIME,
    null,
    'You are not allowed to retrieve metrics in real-time',
    Response::HTTP_FORBIDDEN
)]
final class FindSingleMetricController extends AbstractController
{
    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    /**
     * @param Request $request
     * @param FindSingleMetric $useCase
     * @param FindSingleMetricPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        FindSingleMetric $useCase,
        FindSingleMetricPresenter $presenter
    ): Response {
        $hostId = $request->attributes->getInt('hostId');
        $serviceId = $request->attributes->getInt('serviceId');
        $metricName = (string) $request->attributes->get('metricName');

        $input = new FindSingleMetricInput($hostId, $serviceId, $metricName);

        // Validate the input HTTP‐level
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            $presenter->presentResponse(new ValidationErrorResponse($violations));

            return $presenter->show();
        }

        $request = new FindSingleMetricRequest(
            hostId: $hostId,
            serviceId: $serviceId,
            metricName: $metricName
        );

        $useCase($request, $presenter);

        return $presenter->show();
    }
}
