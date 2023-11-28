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

namespace Core\Dashboard\Playlist\Application\UseCase\DeletePlaylist;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistRepositoryInterface;

final class DeletePlaylist
{
    use LoggerTrait;

    /**
     * @param ReadPlaylistRepositoryInterface $readPlaylistRepository
     * @param WritePlaylistRepositoryInterface $writePlaylistRepository
     * @param ReadPlaylistShareRepositoryInterface $readPlaylistShareRepository
     * @param DashboardRights $rights
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadPlaylistRepositoryInterface $readPlaylistRepository,
        private readonly WritePlaylistRepositoryInterface $writePlaylistRepository,
        private readonly ReadPlaylistShareRepositoryInterface $readPlaylistShareRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param integer $playlistId
     * @param DeletePlaylistPresenterInterface $presenter
     */
    public function __invoke(int $playlistId, DeletePlaylistPresenterInterface $presenter): void {
        try {
            if (! $this->rights->canCreate()) {
                $this->error('Access Forbidden');
                $presenter->presentResponse(new ForbiddenResponse(
                    PlaylistException::accessNotAllowed()->getMessage()
                ));

                return;
            }

            if (! $this->readPlaylistRepository->exists($playlistId)) {
                $this->error('Playlist not found', ['playlist_id' => $playlistId]);
                $presenter->presentResponse(new NotFoundResponse('Playlist'));

                return;
            }

            if (! $this->rights->hasAdminRole()) {
                if (!$this->readPlaylistShareRepository->existsAsEditor($playlistId, $this->user)) {
                    throw PlaylistException::playlistNotSharedAsEditor($playlistId);
                }
            }

            $this->writePlaylistRepository->delete($playlistId);
            $presenter->presentResponse(new NoContentResponse());
        } catch (PlaylistException $ex) {
            $this->error($ex->getMessage());
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error(PlaylistException::errorWhileDeleting()->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse(PlaylistException::errorWhileDeleting()->getMessage()));
        }
    }
}
