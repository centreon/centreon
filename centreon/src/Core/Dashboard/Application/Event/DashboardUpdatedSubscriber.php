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

namespace Core\Dashboard\Application\Event;

use Core\Common\Infrastructure\Upload\FileCollection;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnailRelation\AddDashboardThumbnailRelation;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnailRelation\AddDashboardThumbnailRelationPresenterInterface;
use Core\Media\Application\UseCase\AddMedia\AddMedia;
use Core\Media\Application\UseCase\AddMedia\AddMediaPresenterInterface;
use Core\Media\Application\UseCase\AddMedia\AddMediaRequest;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMedia;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaPresenterInterface;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaRequest;
use Core\Media\Domain\Model\Media;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DashboardUpdatedSubscriber implements EventSubscriberInterface
{
    /**
     * @param UpdateMedia $mediaUpdater
     * @param UpdateMediaPresenterInterface $mediaUpdaterPresenter
     * @param AddMedia $mediaCreator
     * @param AddMediaPresenterInterface $mediaCreatorPresenter
     * @param AddDashboardThumbnailRelation $thumbnailDashboardLinkCreator
     * @param AddDashboardThumbnailRelationPresenterInterface $thumbnailDashboardLinkCreatorPresenter
     */
    public function __construct(
        private readonly UpdateMedia $mediaUpdater,
        private readonly UpdateMediaPresenterInterface $mediaUpdaterPresenter,
        private readonly AddMedia $mediaCreator,
        private readonly AddMediaPresenterInterface $mediaCreatorPresenter,
        private readonly AddDashboardThumbnailRelation $thumbnailDashboardLinkCreator,
        private readonly AddDashboardThumbnailRelationPresenterInterface $thumbnailDashboardLinkCreatorPresenter
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [DashboardUpdatedEvent::class => 'createOrUpdateDashboardThumbnail'];
    }

    /**
     * @param DashboardUpdatedEvent $event
     *
     * @throws \Exception
     */
    public function createOrUpdateDashboardThumbnail(DashboardUpdatedEvent $event): void
    {
        $thumbnail = $event->getMedia();

        if ($thumbnail instanceof UploadedFile) {
            ($this->mediaCreator)(
                $this->createAddMediaRequest($event->getDirectory(), $thumbnail),
                $this->mediaCreatorPresenter,
            );

            $data = $this->mediaCreatorPresenter->getPresentedData();

            if (count($data['errors']) > 0) {
                throw new \Exception($data['errors'][0]['reason']);
            }
                ($this->thumbnailDashboardLinkCreator)(
                    $event->getDashboardId(),
                    $data['result'][0]['id'],
                    $this->thumbnailDashboardLinkCreatorPresenter,
                );

                $responseStatus = $this->thumbnailDashboardLinkCreatorPresenter->getResponseStatus();

                if ($responseStatus !== null) {
                    throw new \Exception($responseStatus->getMessage());
                }

        } else {
            ($this->mediaUpdater)(
                $thumbnail->getId(),
                $this->createUpdateMediaRequest($thumbnail),
                $this->mediaUpdaterPresenter,
            );

            $responseStatus = $this->mediaUpdaterPresenter->getResponseStatus();

            if ($responseStatus !== null) {
                throw new \Exception($responseStatus->getMessage());
            }
        }
    }

    /**
     * @param UploadedFile $thumbnail
     * @param string $directory
     *
     * @return AddMediaRequest
     */
    private function createAddMediaRequest(string $directory, UploadedFile $thumbnail): AddMediaRequest
    {
        $fileIterator = new FileCollection();
        $fileIterator->addFile($thumbnail);

        $request = new AddMediaRequest($fileIterator->getFiles());
        $request->directory = $directory;

        return $request;
    }

    /**
     * @param Media $thumbnail
     *
     * @return UpdateMediaRequest
     */
    private function createUpdateMediaRequest(Media $thumbnail): UpdateMediaRequest
    {
        if ($thumbnail->getData() === null) {
            throw new \Exception(sprintf('No data found for media %s', $thumbnail->getFilename()));
        }

        return new UpdateMediaRequest($thumbnail->getFilename(), $thumbnail->getData());
    }
}
