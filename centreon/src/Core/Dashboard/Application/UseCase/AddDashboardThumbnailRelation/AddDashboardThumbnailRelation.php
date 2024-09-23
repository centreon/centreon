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

namespace Core\Dashboard\Application\UseCase\AddDashboardThumbnailRelation;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;

final class AddDashboardThumbnailRelation
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadMediaRepositoryInterface $mediaRepository,
    ) {
    }

    /**
     * @param int $dashboardId
     * @param int $mediaId
     * @param AddDashboardThumbnailRelationPresenterInterface $presenter
     */
    public function __invoke(int $dashboardId, int $mediaId, AddDashboardThumbnailRelationPresenterInterface $presenter): void
    {
        try {
            $thumbnail = $this->mediaRepository->findById($mediaId);

            if ($thumbnail === null) {
                $presenter->presentResponse(new NotFoundResponse('Media'));
            } else {
                $dashboard = $this->readDashboardRepository->findOne($dashboardId);

                if ($dashboard === null) {
                    $presenter->presentResponse(new NotFoundResponse('Dashboard'));

                    return;
                }
                $this->writeDashboardRepository->addThumbnailRelation($dashboard, $thumbnail);
            }

            $presenter->presentResponse(new NoContentResponse());
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileLinkingDashboardAndThumbnail()));
        }
    }
}
