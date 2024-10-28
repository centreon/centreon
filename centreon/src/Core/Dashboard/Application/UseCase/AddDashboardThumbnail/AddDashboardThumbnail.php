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

namespace Core\Dashboard\Application\UseCase\AddDashboardThumbnail;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\NewMedia;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

readonly final class AddDashboardThumbnail
{
    use LoggerTrait;

    /**
     * @param WriteDashboardRepositoryInterface $writeDashboardRepository
     * @param WriteMediaRepositoryInterface $writeMediaRepository
     * @param ReadDashboardRepositoryInterface $readDashboardRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ContactInterface $user
     */
    public function __construct(
        private WriteDashboardRepositoryInterface $writeDashboardRepository,
        private WriteMediaRepositoryInterface $writeMediaRepository,
        private ReadDashboardRepositoryInterface $readDashboardRepository,
        private DataStorageEngineInterface $dataStorageEngine,
        private ContactInterface $user
    ) {
    }

    /**
     * @param AddDashboardThumbnailRequest $request
     * @param AddDashboardThumbnailPresenterInterface $presenter
     */
    public function __invoke(
        AddDashboardThumbnailRequest $request,
        AddDashboardThumbnailPresenterInterface $presenter,
    ): void {
        try {
            $dashboard = $this->user->isAdmin()
                ? $this->readDashboardRepository->findOne($request->dashboardId)
                : $this->readDashboardRepository->findOneByContact($request->dashboardId, $this->user);

            if ($dashboard === null) {
                $presenter->presentResponse(new ErrorResponse(
                    DashboardException::theDashboardDoesNotExist($request->dashboardId),
                ));

                return;
            }

            $media = $this->createMediaFromRequest($request);

            $mediaId = $this->addThumbnail($media);

            $this->writeDashboardRepository->addThumbnailRelation($dashboard->getId(), $mediaId);

            $presenter->presentResponse(new NoContentResponse());
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileThumbnailToDashboard()));
        }
    }

    /**
     * @param AddDashboardThumbnailRequest $request
     * @throws FileException
     * @return NewMedia
     */
    private function createMediaFromRequest(AddDashboardThumbnailRequest $request): NewMedia
    {
        return new NewMedia($request->filename, $request->directory, $request->thumbnail->getContent());
    }

    /**
     * @param NewMedia $thumbnail
     *
     * @throws \Throwable
     * @return int
     */
    private function addThumbnail(NewMedia $thumbnail): int
    {
        try {
            $this->dataStorageEngine->startTransaction();
            $mediaId = $this->writeMediaRepository->add($thumbnail);
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $exception) {
            $this->dataStorageEngine->rollbackTransaction();

            throw $exception;
        }

        return $mediaId;
    }
}
