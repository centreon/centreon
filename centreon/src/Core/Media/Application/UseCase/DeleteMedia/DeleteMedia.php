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

namespace Core\Media\Application\UseCase\DeleteMedia;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Contact\Domain\AdminResolver;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteMedia
{
    use LoggerTrait;

    /**
     * @param ReadMediaRepositoryInterface $readMediaRepository
     * @param WriteMediaRepositoryInterface $writeMediaRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadImageFolderRepositoryInterface $readImageFolderRepository
     * @param ContactInterface $user
     * @param AdminResolver $adminResolver
     */
    public function __construct(
        private readonly ReadMediaRepositoryInterface $readMediaRepository,
        private readonly WriteMediaRepositoryInterface $writeMediaRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadImageFolderRepositoryInterface $readImageFolderRepository,
        private readonly ContactInterface $user,
        private readonly AdminResolver $adminResolver,
    ) {
    }

    /**
     * @param int $mediaId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $mediaId, PresenterInterface $presenter): void
    {
        try {
            $media = null;

            if ($this->adminResolver->isAdmin($this->user)) {
                $media = $this->readMediaRepository->findById($mediaId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

                if (
                    $this->readImageFolderRepository->hasAccessToAllImageFolders($accessGroups)
                    || $this->readMediaRepository->existsByAccessGroups($mediaId, $accessGroups)
                ) {
                    $media = $this->readMediaRepository->findById($mediaId);
                }
            }

            if ($media === null) {
                $this->error('Media not found', ['media_id' => $mediaId]);
                $presenter->setResponseStatus(new NotFoundResponse('Media'));

                return;
            }

            $this->info(message: "Delete media #{$mediaId}");
            $this->writeMediaRepository->delete($media);

            $presenter->setResponseStatus(new NoContentResponse());
            $this->info(
                'Media deleted',
                [
                    'media_id' => $mediaId,
                    'user_id' => $this->user->getId(),
                ]
            );
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(MediaException::errorWhileDeletingMedia()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }
}
