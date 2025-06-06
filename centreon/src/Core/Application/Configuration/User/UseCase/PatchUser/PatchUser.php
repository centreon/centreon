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

namespace Core\Application\Configuration\User\UseCase\PatchUser;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\Session\Repository\ReadSessionRepositoryInterface;
use Core\Application\Common\Session\Repository\WriteSessionRepositoryInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Configuration\User\Exception\UserException;
use Core\Application\Configuration\User\Repository\ReadUserRepositoryInterface;
use Core\Application\Configuration\User\Repository\WriteUserRepositoryInterface;

final class PatchUser
{
    use LoggerTrait;

    /**
     * @param ReadUserRepositoryInterface $readUserRepository
     * @param WriteUserRepositoryInterface $writeUserRepository
     * @param ReadSessionRepositoryInterface $readSessionRepository
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     */
    public function __construct(
        private ReadUserRepositoryInterface $readUserRepository,
        private WriteUserRepositoryInterface $writeUserRepository,
        private ReadSessionRepositoryInterface $readSessionRepository,
        private WriteSessionRepositoryInterface $writeSessionRepository
    ) {
    }

    /**
     * @param PatchUserRequest $request
     * @param PatchUserPresenterInterface $presenter
     */
    public function __invoke(PatchUserRequest $request, PatchUserPresenterInterface $presenter): void
    {
        $this->info('Updating user', ['request' => $request]);
        try {
            try {
                $this->debug('Find user', ['user_id' => $request->userId]);
                if (($user = $this->readUserRepository->findById($request->userId)) === null) {
                    $this->userNotFound($request->userId, $presenter);

                    return;
                }
            } catch (\Throwable $ex) {
                $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

                throw UserException::errorWhileSearchingForUser($ex);
            }

            if ($request->theme !== null) {
                try {
                    if (($themes = $this->readUserRepository->findAvailableThemes()) === []) {
                        $this->unexpectedError('Abnormally empty list of themes', $presenter);

                        return;
                    }

                    $this->debug('User themes available', ['themes' => $themes]);

                    if (! in_array($request->theme, $themes, true)) {
                        $this->themeNotFound($request->theme, $presenter);

                        return;
                    }
                } catch (\Throwable $ex) {
                    $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

                    throw UserException::errorInReadingUserThemes($ex);
                }

                $user->setTheme($request->theme);
                $this->debug('New theme', ['theme' => $request->theme]);
            }

            if ($request->userInterfaceDensity !== null) {
                try {
                    $user->setUserInterfaceDensity($request->userInterfaceDensity);
                    $this->debug('New user interface view mode', ['mode' => $request->userInterfaceDensity]);
                } catch (\InvalidArgumentException $ex) {
                    $this->userInterfaceViewModeUnhandled($request->userInterfaceDensity, $presenter);

                    return;
                }
            }

            try {
                $this->writeUserRepository->update($user);
                $this->updateUserSessions($request);
            } catch (\Throwable $ex) {
                throw UserException::errorOnUpdatingUser($ex);
            }
            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $this->unexpectedError($ex->getMessage(), $presenter);
        }
    }

    /**
     * Update all user sessions.
     *
     * @param PatchUserRequest $request
     *
     * @throws \Throwable
     */
    private function updateUserSessions(PatchUserRequest $request): void
    {
        $userSessionIds = $this->readSessionRepository->findSessionIdsByUserId($request->userId);

        foreach ($userSessionIds as $sessionId) {
            /**
             * @var \Centreon|null $centreon
             */
            $centreon = $this->readSessionRepository->getValueFromSession(
                $sessionId,
                'centreon'
            );
            if ($centreon === null) {
                continue;
            }

            $centreon->user->theme = $request->theme;
            $this->writeSessionRepository->updateSession(
                $sessionId,
                'centreon',
                $centreon
            );
        }
    }

    /**
     * Handle user not found.
     *
     * @param int $userId
     * @param PresenterInterface $presenter
     */
    private function userNotFound(int $userId, PresenterInterface $presenter): void
    {
        $this->error(
            'User not found',
            ['user_id' => $userId]
        );
        $presenter->setResponseStatus(new NotFoundResponse('User'));
    }

    /**
     * @param string $errorMessage
     * @param PresenterInterface $presenter
     */
    private function unexpectedError(string $errorMessage, PresenterInterface $presenter): void
    {
        $this->error($errorMessage);
        $presenter->setResponseStatus(new ErrorResponse($errorMessage));
    }

    /**
     * @param string $theme
     * @param PresenterInterface $presenter
     */
    private function themeNotFound(string $theme, PresenterInterface $presenter): void
    {
        $this->error('Requested theme not found', ['theme' => $theme]);
        $presenter->setResponseStatus(new ErrorResponse(_('Requested theme not found')));
    }

    /**
     * @param string $userInterfaceViewMode
     * @param PresenterInterface $presenter
     */
    private function userInterfaceViewModeUnhandled(string $userInterfaceViewMode, PresenterInterface $presenter): void
    {
        $this->error('Requested user interface view mode not handled', ['mode' => $userInterfaceViewMode]);
        $presenter->setResponseStatus(new ErrorResponse(_('Requested user interface view mode not handled')));
    }
}
