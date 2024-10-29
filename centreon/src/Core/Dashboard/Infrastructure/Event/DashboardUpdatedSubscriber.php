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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly final class DashboardUpdatedSubscriber implements EventSubscriberInterface
{
    /**
     * @param UpdateMedia $thumbnailUpdater
     * @param UpdateMediaPresenterInterface $thumbnailUpdaterPresenter
     * @param AddDashboardThumbnail $thumbnailCreator
     * @param AddDashboardThumbnailPresenterInterface $thumbnailCreatorPresenter
     */
    public function __construct(
        private UpdateMedia $thumbnailUpdater,
        private UpdateMediaPresenterInterface $thumbnailUpdaterPresenter,
        private AddDashboardThumbnail $thumbnailCreator,
        private AddDashboardThumbnailPresenterInterface $thumbnailCreatorPresenter,
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
        if ($event->getThumbnailId() === null) {
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
                $event->getThumbnailId(),
                $this->createUpdateMediaRequest($event),
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
    private function createAddDashboardThumbnailRequestFromEvent(
        DashboardUpdatedEvent $event,
    ): AddDashboardThumbnailRequest {
        return new AddDashboardThumbnailRequest(
            $event->getDashboardId(),
            $event->getDirectory(),
            $event->getFilename(),
            $event->getContent(),
        );
    }

    /**
     * @param DashboardUpdatedEvent $event
     *
     * @return UpdateMediaRequest
     */
    private function createUpdateMediaRequest(DashboardUpdatedEvent $event): UpdateMediaRequest
    {
        if ($event->getContent() === '') {
            throw new \Exception(sprintf('No data found for media %s', $event->getFilename()));
        }

        return new UpdateMediaRequest($event->getFilename(), $event->getContent());
    }
}
