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

namespace Core\Dashboard\Playlist\Application\UseCase\SharePlaylist;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistShareRepositoryInterface;

final class SharePlaylist
{
    use LoggerTrait;

    /**
     * @param DashboardRights $rights
     * @param SharePlaylistValidator $validator
     * @param ContactInterface $user
     * @param WritePlaylistShareRepositoryInterface $shareRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ReadContactRepositoryInterface $contactRepository
     */
    public function __construct(
        private readonly DashboardRights $rights,
        private readonly SharePlaylistValidator $validator,
        private readonly ContactInterface $user,
        private readonly WritePlaylistShareRepositoryInterface $shareRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly ReadContactRepositoryInterface $contactRepository,
    ) {
    }

    /**
     * @param int $playlistId
     * @param SharePlaylistRequest $request
     * @param SharePlaylistPresenterInterface $presenter
     */
    public function __invoke(
        int $playlistId,
        SharePlaylistRequest $request,
        SharePlaylistPresenterInterface $presenter
    ): void {
        try {
            if (! $this->rights->canCreate()) {
                $this->error('Access Forbidden');
                $presenter->presentResponse(new ForbiddenResponse(
                    PlaylistException::accessNotAllowed()->getMessage()
                ));

                return;
            }

            $this->info('validating Playlist exists', ['playlist_id' => $playlistId]);
            $this->validator->validatePlaylist($playlistId, $this->user, $this->rights->hasAdminRole());

            $contactIds = array_map(fn(array $contact): int => $contact['id'], $request->contacts);
            $contactGroupIds = array_map(fn(array $contactGroup): int => $contactGroup['id'], $request->contactGroups);

            /**
             * If the user is not admin, we need to retrieve his contactgroups,
             * and the other users of his contactgroups.
             *
             * it overwrites only those contacts or contactgroups that he is authorized to handle.
             *
             * E.G if a user under ACL send a body with contacts: [], we want to erase only the contact he has access,
             * not ALL the contacts.
             */
            if (! $this->user->isAdmin()) {
                $this->info(
                    'retrieving user contactgroups and contact in contactgroups',
                    ['id' => $this->user->getId()]
                );
                $userContactGroups = $this->contactGroupRepository->findAllByUserId($this->user->getId());
                $userContactGroupIds = array_map(
                    fn (ContactGroup $contactGroup): int => $contactGroup->getId(), $userContactGroups
                );
                $contactIdsInUserContactGroups = $this->contactRepository->findContactIdsByContactGroups(
                    $userContactGroupIds
                );
            }

            $this->info('validating contacts', ['contact_ids' => implode(', ',$contactIds)]);
            $this->validator->validateContacts(
                $contactIds,
                $this->rights->hasAdminRole(),
                $contactIdsInUserContactGroups ?? []
            );

            $this->info('validating contact groups', ['contactgroup_ids' => implode(', ',$contactGroupIds)]);
            $this->validator->validateContactGroups(
                $contactGroupIds,
                $this->rights->hasAdminRole(),
                $userContactGroupIds ?? []
            );

            if (! $this->user->IsAdmin()) {
                $this->info('update playlist shares as non admin', ['playlist_id' => $playlistId]);
                $this->updatePlaylistShareAsNonAdmin(
                    $playlistId,
                    $request->contacts,
                    $request->contactGroups,
                    $contactIdsInUserContactGroups,
                    $userContactGroupIds
                );
            } else {
                $this->info('update playlist shares as admin', ['playlist_id' => $playlistId]);
                $this->updatePlaylistShare($playlistId, $request->contacts, $request->contactGroups);
            }

            $presenter->presentResponse(New NoContentResponse());
        } catch (PlaylistException $ex){
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    PlaylistException::CODE_NOT_FOUND => new NotFoundResponse('Playlist'),
                    default => new InvalidArgumentResponse($ex),
                }
            );
        } catch (\Throwable $ex) {
            $this->error(PlaylistException::errorWhileUpdatingShares()->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse(PlaylistException::errorWhileUpdatingShares()));
        }
    }

    /**
     * Update Playlist shares.
     *
     * @param int $playlistId
     * @param array{}|array<array{id: int, role: string}> $contacts
     * @param array{}|array<array{id: int, role: string}> $contactGroups
     *
     * @throws \Throwable
     */
    private function updatePlaylistShare(int $playlistId, array $contacts, array $contactGroups): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $this->shareRepository->deletePlaylistShares($playlistId);
            if ([] !== $contacts) {
                $this->shareRepository->addPlaylistContactShares($playlistId, $contacts);
            }
            if ([] !== $contactGroups) {
                $this->shareRepository->addPlaylistContactGroupShares($playlistId, $contactGroups);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->debug('Rollback transaction');
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Update playlist shares for a non admin user.
     *
     * @param integer $playlistId
     * @param array{}|array<array{id: int, role: string}> $contacts
     * @param array{}|array<array{id: int, role: string}> $contactGroups
     * @param int[] $contactsInUserContactGroups
     * @param int[] $userContactGroups
     *
     * @throws \Throwable
     */
    private function updatePlaylistShareAsNonAdmin(
        int $playlistId,
        array $contacts,
        array $contactGroups,
        array $contactsInUserContactGroups,
        array $userContactGroups
    ) {
        try {
            $this->dataStorageEngine->startTransaction();
            $this->shareRepository->deletePlaylistSharesByContactIds($playlistId, $contactsInUserContactGroups);
            $this->shareRepository->deletePlaylistSharesByContactGroupIds($playlistId, $userContactGroups);
            if ([] !== $contacts) {
                $this->shareRepository->addPlaylistContactShares($playlistId, $contacts);
            }
            if ([] !== $contactGroups) {
                $this->shareRepository->addPlaylistContactGroupShares($playlistId, $contactGroups);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->debug('Rollback transaction');
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }
}
