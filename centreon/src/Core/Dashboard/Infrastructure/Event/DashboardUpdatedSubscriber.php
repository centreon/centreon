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

namespace Core\Dashboard\Infrastructure\Event;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Dashboard\Application\Event\DashboardUpdatedEvent;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnail\AddDashboardThumbnail;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnail\AddDashboardThumbnailPresenterInterface;
use Core\Dashboard\Application\UseCase\AddDashboardThumbnail\AddDashboardThumbnailRequest;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMedia;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaPresenterInterface;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaRequest;
use Core\Media\Domain\Model\Media;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DashboardUpdatedSubscriber implements EventSubscriberInterface
{
    /**
     * @param UpdateMedia $thumbnailUpdater
     * @param UpdateMediaPresenterInterface $thumbnailUpdaterPresenter
     * @param AddDashboardThumbnail $thumbnailCreator
     * @param AddDashboardThumbnailPresenterInterface $thumbnailCreatorPresenter
     */
    public function __construct(
        private readonly UpdateMedia $thumbnailUpdater,
        private readonly UpdateMediaPresenterInterface $thumbnailUpdaterPresenter,
        private readonly AddDashboardThumbnail $thumbnailCreator,
        private readonly AddDashboardThumbnailPresenterInterface $thumbnailCreatorPresenter,
    ) {
    }

    /**
     * @inheritDoc
     */
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
        $thumbnail = $event->getThumbnail();

        /**
         * IF $thumbnail is an instance of UploadedFile it means that the dashboard updated does not have
         * any thumbnail created yet. The thumbnailCreator will create the thumbnail (media) on the
         * database + filesystem and then add the relation between the thumbnail and the dashboard updated.
         *
         * Otherwise only the content of the thumbnail (media) on the filesystem will be updated.
         */
        if ($thumbnail instanceof UploadedFile) {
            ($this->thumbnailCreator)(
                $this->createAddDashboardThumbnailRequestFromEvent($event),
                $this->thumbnailCreatorPresenter
            );

            /** @var AbstractPresenter $thumbnailCreatorPresenter */
            $thumbnailCreatorPresenter = $this->thumbnailCreatorPresenter;
            $responseStatus = $thumbnailCreatorPresenter->getResponseStatus();

            if ($responseStatus !== null) {
                throw new \Exception($responseStatus->getMessage());
            }
        } else {
            ($this->thumbnailUpdater)(
                $thumbnail->getId(),
                $this->createUpdateMediaRequest($thumbnail),
                $this->thumbnailUpdaterPresenter,
            );

            /** @var AbstractPresenter $mediaUpdaterPresenter */
            $mediaUpdaterPresenter = $this->thumbnailUpdaterPresenter;
            $responseStatus = $mediaUpdaterPresenter->getResponseStatus();

            if ($responseStatus !== null) {
                throw new \Exception($responseStatus->getMessage());
            }
        }
    }

    /**
     * @param DashboardUpdatedEvent $event
     *
     * @return AddDashboardThumbnailRequest
     */
    private function createAddDashboardThumbnailRequestFromEvent(DashboardUpdatedEvent $event): AddDashboardThumbnailRequest
    {
        /** @var UploadedFile $thumbnail */
        $thumbnail = $event->getThumbnail();

        return new AddDashboardThumbnailRequest($event->getDashboardId(), $event->getDirectory(), $thumbnail);
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
