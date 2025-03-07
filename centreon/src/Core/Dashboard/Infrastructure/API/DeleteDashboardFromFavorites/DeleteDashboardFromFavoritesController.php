<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\API\DeleteDashboardFromFavorites;

use Centreon\Application\Controller\AbstractController;
use Core\Dashboard\Application\UseCase\DeleteDashboardFromFavorites\DeleteDashboardFromFavorites;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(
    'dashboard_access',
    null,
    'You do not have sufficient rights to remove the dashboard from favorites'
)]
final class DeleteDashboardFromFavoritesController extends AbstractController
{
    /**
     * @param int $dashboardId
     * @param DeleteDashboardFromFavorites $useCase
     * @return Response
     */
    public function __invoke(
        int $dashboardId,
        DeleteDashboardFromFavorites $useCase
    ): Response {
        return $this->createResponse($useCase($dashboardId));
    }
}
