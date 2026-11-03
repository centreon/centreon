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

namespace Core\Media\Application\UseCase\GetMedia;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Domain\AdminResolver;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Media\Domain\Model\Media;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class GetMedia
{
    use LoggerTrait;

    /**
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMediaRepositoryInterface $readMediaRepository
     * @param ReadImageFolderRepositoryInterface $readImageFolderRepository
     * @param ContactInterface $user
     * @param AdminResolver $adminResolver
     */
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMediaRepositoryInterface $readMediaRepository,
        private readonly ReadImageFolderRepositoryInterface $readImageFolderRepository,
        private readonly ContactInterface $user,
        private readonly AdminResolver $adminResolver,
    ) {
    }

    public function __invoke(int $mediaId): GetMediaResponse|ResponseStatusInterface
    {
        try {
            $media = null;

            if ($this->adminResolver->isAdmin($this->user)) {
                $media = $this->readMediaRepository->findById($mediaId);

            } else {
                $media = $this->readMediaRepository->findById($mediaId);

                if ($media !== null && ! $this->canRead($media)) {
                    $media = null;
                }
            }

            if ($media === null) {
                return new NotFoundResponse('Media');
            }

            return new GetMediaResponse($media);
        } catch (\Throwable $ex) {
            $this->error(
                "Error while retrieving a media: {$ex->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'media_id' => $mediaId,
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(MediaException::errorWhileRetrieving());
        }
    }

    /**
     * The media of the system folders are readable whatever the user's image folder access.
     *
     * @throws \Throwable
     */
    private function canRead(Media $media): bool
    {
        if (in_array($media->getDirectory(), ImageFolder::SYSTEM_FOLDER_NAMES, true)) {
            return true;
        }

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

        return $this->readImageFolderRepository->hasAccessToAllImageFolders($accessGroups)
            || $this->readMediaRepository->existsByAccessGroups($media->getId(), $accessGroups);
    }
}
