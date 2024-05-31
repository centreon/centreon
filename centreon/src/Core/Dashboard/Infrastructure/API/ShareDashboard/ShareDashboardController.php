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

namespace Core\Dashboard\Infrastructure\API\ShareDashboard;

use Centreon\Application\Controller\AbstractController;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboard;
use Core\Dashboard\Application\UseCase\ShareDashboard\ShareDashboardRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShareDashboardController extends AbstractController
{
    public function __invoke(
        int $dashboardId,
        ShareDashboardPresenter $presenter,
        ShareDashboard $useCase,
        Request $request
    ): Response {
        $shareDashboardRequest = $this->createRequest($dashboardId, $request);
        $useCase($shareDashboardRequest, $presenter);

        return $presenter->show();
    }

    /**
     * @param int $dashboardId
     * @param Request $request
     *
     * @return ShareDashboardRequest
     */
    private function createRequest(int $dashboardId, Request $request): ShareDashboardRequest
    {
        /**
         * @var array{
         *  contacts: array<array{
         *      id:int,
         *      role:string
         *  }>,
         *  contact_groups: array<array{
         *      id:int,
         *      role:string
         *  }>
         * } $requestBody
         */
        $requestBody = $this->validateAndRetrieveDataSent(
            $request,
            __DIR__ . '/ShareDashboardSchema.json'
        );

        $shareDashboardRequest = new ShareDashboardRequest();
        $shareDashboardRequest->dashboardId = $dashboardId;
        $shareDashboardRequest->contacts = $requestBody['contacts'];
        $shareDashboardRequest->contactGroups = $requestBody['contact_groups'];

        return $shareDashboardRequest;
    }
}