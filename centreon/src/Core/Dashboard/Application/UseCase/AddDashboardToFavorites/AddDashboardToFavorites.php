<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\UseCase\AddDashboardToFavorites;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\UserProfile\Application\Repository\ReadUserProfileRepositoryInterface;
use Core\UserProfile\Application\Repository\WriteUserProfileRepositoryInterface;
use InvalidArgumentException;
use Throwable;

final class AddDashboardToFavorites
{
    use LoggerTrait;

    /**
     * @param ReadUserProfileRepositoryInterface $userProfileReader
     * @param WriteUserProfileRepositoryInterface $userProfileWriter
     * @param ReadDashboardRepositoryInterface $dashboardReader
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadUserProfileRepositoryInterface $userProfileReader,
        private readonly WriteUserProfileRepositoryInterface $userProfileWriter,
        private readonly ReadDashboardRepositoryInterface $dashboardReader,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param AddDashboardToFavoritesRequest $request
     * @return ResponseStatusInterface
     */
    public function __invoke(AddDashboardToFavoritesRequest $request): ResponseStatusInterface
    {
        try {
            $this->assertDashboardId($request);

            $favorites = [];
            $profileId = $this->addDefaultUserProfileForUser();
            $profile = $this->userProfileReader->findByContact($this->user);
            if (! is_null($profile)) {
                $favorites = $profile->getFavoriteDashboards();
                $profileId = $profile->getId();
            }

            if (in_array($request->dashboardId, $favorites, true)) {
                $this->error(
                    'Dashboard already set as favorite for user',
                    [
                        'dashboard_id' => $request->dashboardId,
                        'user_id' => $this->user->getId(),
                    ]
                );

                return new ConflictResponse(DashboardException::dashboardAlreadySetAsFavorite($request->dashboardId));
            }

            $this->userProfileWriter->addDashboardAsFavorites(
                profileId: $profileId,
                dashboardId: $request->dashboardId,
            );

            return new NoContentResponse();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            $this->error(
                "Error while adding dashboard as favorite for user : {$exception->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'dashboard_id' => $request->dashboardId,
                    'favorite_dashboards' => $favorites ?? [],
                    'exception' => ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()],
                ]
            );

            return new InvalidArgumentResponse($exception);
        } catch (Throwable $exception) {
            $this->error(
                "Error while adding dashboard as favorite for user : {$exception->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'dashboard_id' => $request->dashboardId,
                    'profile_id' => $profileId ?? null,
                    'favorite_dashboards' => $favorites ?? [],
                    'exception' => ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()],
                ]
            );

            return new ErrorResponse($exception);
        }
    }

    /**
     * @throws Throwable
     * @return int
     */
    private function addDefaultUserProfileForUser(): int
    {
        return $this->userProfileWriter->addDefaultProfileForUser(contact: $this->user);
    }

    /**
     * @param AddDashboardToFavoritesRequest $request
     *
     * @throws Throwable
     * @throws DashboardException
     */
    private function assertDashboardId(AddDashboardToFavoritesRequest $request): void
    {
        if (! $this->dashboardReader->existsOne($request->dashboardId)) {
            throw DashboardException::theDashboardDoesNotExist($request->dashboardId);
        }
    }
}
