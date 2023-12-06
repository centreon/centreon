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

namespace Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Domain\Model\DashboardOrder;
use Core\Dashboard\Playlist\Domain\Model\NewPlaylist;
use Core\Dashboard\Playlist\Domain\Model\Playlist;

final class CreatePlaylist
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param CreatePlaylistValidator $validator
     * @param WritePlaylistRepositoryInterface $writePlaylistRepository
     * @param ReadPlaylistRepositoryInterface $readPlaylistRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ContactRepositoryInterface $contactRepository
     * @param ReadDashboardRepositoryInterface $dashboardRepository
     * @param DashboardRights $rights
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly CreatePlaylistValidator $validator,
        private readonly WritePlaylistRepositoryInterface $writePlaylistRepository,
        private readonly ReadPlaylistRepositoryInterface $readPlaylistRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly ReadDashboardRepositoryInterface $dashboardRepository,
        private readonly DashboardRights $rights
    ) {
    }

    /**
     * @param CreatePlaylistPresenterInterface $presenter
     * @param CreatePlaylistRequest $request
     */
    public function __invoke(CreatePlaylistPresenterInterface $presenter, CreatePlaylistRequest $request): void
    {
        try {
            if (! $this->rights->canCreate()) {
                $presenter->presentResponse(new ForbiddenResponse(
                    PlaylistException::accessNotAllowed()->getMessage()
                ));

                return;
            }

            $this->validateNameAndDashboards($request);
            $newPlaylist = (new NewPlaylist(
                $request->name,
                $request->rotationTime,
                $request->isPublic,
                $this->user->getId()
            ))
            ->setDescription($request->description);

            $newDashboardOrders = $this->createDashboardsOrder($request->dashboards);
            $playlistId = $this->writePlaylist($newPlaylist, $newDashboardOrders);
            $this->info('retrieving new playlist');
            $playlist = $this->readPlaylistRepository->find($playlistId);
            if (! $playlist) {
                $this->error('Unable to retrieve the newly created playlist');
                $presenter->presentResponse(
                    new ErrorResponse(PlaylistException::errorWhileRetrieving())
                );

                return;
            }
            $author = $this->contactRepository->findById($this->user->getId());
            $dashboards = $this->dashboardRepository->findByIds($playlist->getDashboardIds());
            $dashboardOrders = $this->readPlaylistRepository->findDashboardOrders($playlistId, $dashboards);

            $presenter->presentResponse($this->createResponse($playlist, $author, $dashboardOrders));
        } catch (AssertionFailedException | PlaylistException $ex) {
            $this->error('An error occurred when creating playlist', ['trace' => (string) $ex]);
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    PlaylistException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new InvalidArgumentResponse($ex),
                }
            );
        } catch (\Throwable $ex) {
            $this->error('An error occurred when creating playlist', ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse(PlaylistException::errorWhileCreating()));
        }
    }

    /**
     * @param Playlist $playlist
     * @param Contact|null $contact
     * @param DashboardOrder[] $dashboardsOrder
     * @return CreatePlaylistResponse
     */
    private function createResponse(
        Playlist $playlist,
        ?Contact $contact,
        array $dashboardsOrder
    ): CreatePlaylistResponse {
        $response = new CreatePlaylistResponse();

        $response->id = $playlist->getId();
        $response->name = $playlist->getName();
        $response->description = $playlist->getDescription();
        $response->dashboards = array_map(function (DashboardOrder $dashboardOrder) {
            return [
                'id' => $dashboardOrder->getDashboardId(),
                'order' => $dashboardOrder->getOrder(),
            ];
        }, $dashboardsOrder);
        $response->rotationTime = $playlist->getRotationTime();
        $response->isPublic = $playlist->isPublic();
        if ($contact !== null) {
            $response->author = [
                'id' => $contact->getId(),
                'name' => $contact->getAlias(),
            ];
        }
        $response->createdAt = $playlist->getCreatedAt();

        return $response;
    }

    /**
     * Validate that name is unique and dashboards exists.
     *
     * @param CreatePlaylistRequest $request
     *
     * @throws PlaylistException|\Throwable
     */
    private function validateNameAndDashboards(CreatePlaylistRequest $request): void
    {
        $this->info(
            'Validating playlist name and dashboards',
            [
                'playlist_name' => $request->name,
                'dashboards' => $request->dashboards,
            ]
        );
        $this->validator->validatePlaylistNameIsUnique($request->name);
        if ([] !== $request->dashboards) {
            $dashboardIds = array_map(static fn (array $dashboard): int => $dashboard['id'], $request->dashboards);
            $this->validator->validateDashboardExists($dashboardIds);
            $this->validator->validateDashboardIsUnique($dashboardIds);
            if (! $this->rights->hasAdminRole()) {
                $this->validator->validateUserHasAccessToDashboards($dashboardIds, $this->user);
            }
        }
    }

    /**
     * @param array<array{id:int, order:int}> $requestDashboardsOrder
     * @return DashboardOrder[]
     */
    private function createDashboardsOrder(array $requestDashboardsOrder): array
    {
        $dashboardsOrder = [];
        foreach ($requestDashboardsOrder as $dashboardOrder) {
            $dashboardsOrder[] = new DashboardOrder($dashboardOrder['id'], $dashboardOrder['order']);
        }

        return $dashboardsOrder;
    }

    /**
     * @param NewPlaylist $newPlaylist
     * @param DashboardOrder[] $dashboardsOrder
     * @return int
     * @throws \Throwable
     */
    private function writePlaylist(NewPlaylist $newPlaylist, array $dashboardsOrder): int
    {
        $transactionAlreadyStarted = $this->dataStorageEngine->isAlreadyinTransaction();
        try {
            if (! $transactionAlreadyStarted) {
                $this->info('start transaction');
                $this->dataStorageEngine->startTransaction();
            }
            $this->info('add playlist in data storage');
            $playlistId = $this->writePlaylistRepository->add($newPlaylist);
            $this->info('add dashboards <=> playlist relation in data storage');
            $this->writePlaylistRepository->addDashboardsToPlaylist($playlistId, $dashboardsOrder);
            if (! $transactionAlreadyStarted) {
                $this->info('commit transaction');
                $this->dataStorageEngine->commitTransaction();
            }
        } catch (\Throwable $ex) {
            $this->error('An error occurred', ['trace' => (string) $ex]);
            if (! $transactionAlreadyStarted) {
                $this->info('rollback transaction');
                $this->dataStorageEngine->rollbackTransaction();
            }

            throw $ex;
        }

        return $playlistId;
    }
}
