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

namespace Core\Dashboard\Application\UseCase\DeleteDashboardFromFavorites;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\UserProfile\Application\Repository\ReadUserProfileRepositoryInterface;
use Core\UserProfile\Application\Repository\WriteUserProfileRepositoryInterface;
use Throwable;

final class DeleteDashboardFromFavorites
{
    use LoggerTrait;

    /**
     * @param WriteUserProfileRepositoryInterface $userProfileWriter
     * @param ReadUserProfileRepositoryInterface $userProfileReader
     * @param ReadDashboardRepositoryInterface $dashboardReader
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly WriteUserProfileRepositoryInterface $userProfileWriter,
        private readonly ReadUserProfileRepositoryInterface $userProfileReader,
        private readonly ReadDashboardRepositoryInterface $dashboardReader,
        private readonly ContactInterface $user
    )
    {
    }

    /**
     * @param int $dashboardId
     * @return ResponseStatusInterface
     */
    public function __invoke(int $dashboardId): ResponseStatusInterface
    {
        try {
            $this->assertDashboardId($dashboardId);

            $profile = $this->userProfileReader->findByContact($this->user);

            if ($profile === null) {
                $this->error('Profile not found for user', ['user_id' => $this->user->getId()]);

                return new NotFoundResponse('User profile');
            }

            if (! in_array($dashboardId, $profile->getFavoriteDashboards(), true)) {
                $this->error(
                    'Dashboard is not set as favorite for user',
                    [
                        'user_id' => $this->user->getId(),
                        'dashboard_id' => $dashboardId,
                    ]
                );

                return new NotFoundResponse('Dashboard');
            }

            $this->userProfileWriter->removeDashboardFromFavorites($profile->getId(), $dashboardId);

            return new NoContentResponse();
        } catch (DashboardException $exception) {
            $response = match ($exception->getCode()) {
                DashboardException::CODE_NOT_FOUND => new NotFoundResponse('Dashboard'),
                default => new ErrorResponse($exception)
            };

            $this->error(
                "Error while removing dashboard from user favorite dashboards : {$exception->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'dashboard_id' => $dashboardId,
                    'exception' => ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()],
                ]
            );

            return $response;
        } catch (Throwable $exception) {
            $this->error(
                "Error while removing dashboard from user favorite dashboards : {$exception->getMessage()}",
                [
                    'user_id' => $this->user->getId(),
                    'dashboard_id' => $dashboardId,
                    'exception' => ['message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()],
                ]
            );

            return new ErrorResponse($exception);
        }
    }

    /**
     * @param int $dashboardId
     *
     * @throws Throwable
     * @throws DashboardException
     */
    private function assertDashboardId(int $dashboardId): void
    {
        if (! $this->dashboardReader->existsOne($dashboardId)) {
            throw DashboardException::theDashboardDoesNotExist($dashboardId);
        }
    }
}
