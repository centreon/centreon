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

namespace Core\Dashboard\Infrastructure\API\FindMetricsTop;

use Centreon\Application\Controller\AbstractController;
use Core\Dashboard\Application\UseCase\FindMetricsTop\FindMetricsTop;
use Core\Dashboard\Application\UseCase\FindMetricsTop\FindMetricsTopRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class FindMetricsTopController extends AbstractController
{
    private const METRIC_NAME_PARAMETER = 'metric_name';

    /**
     * @param FindMetricsTop $useCase
     * @param FindMetricsTopPresenter $presenter
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(FindMetricsTop $useCase, FindMetricsTopPresenter $presenter, Request $request): Response
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $useCase($presenter, $this->createRequest($request));

        return $presenter->show();
    }

    /**
     * @param Request $request
     *
     * @return FindMetricsTopRequest
     */
    private function createRequest(Request $request): FindMetricsTopRequest
    {
        $metricName = $request->query->get(self::METRIC_NAME_PARAMETER)
            ?? throw new \InvalidArgumentException("missing mandatory parameter 'metric_name'");
        $findMetricsTopRequest = new FindMetricsTopRequest();

        $findMetricsTopRequest->metricName = (string) $metricName;

        return $findMetricsTopRequest;
    }
}
