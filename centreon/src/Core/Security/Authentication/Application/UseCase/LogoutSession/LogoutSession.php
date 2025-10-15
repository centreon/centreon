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

namespace Core\Security\Authentication\Application\UseCase\LogoutSession;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;

class LogoutSession
{
    /**
     * @param WriteSessionRepositoryInterface $writeSessionRepository
     */
    public function __construct(
        private readonly WriteSessionRepositoryInterface $writeSessionRepository,
    ) {
    }

    /**
     * @param mixed $token
     * @param LogoutSessionPresenterInterface $presenter
     */
    public function __invoke(
        mixed $token,
        LogoutSessionPresenterInterface $presenter,
    ): void {

        if ($token === null || is_string($token) === false) {
            $presenter->setResponseStatus(new ErrorResponse(message: _('No session token provided')));

            return;
        }

        try {
            $this->writeSessionRepository->invalidate();
        } catch (RepositoryException $e) {
            $presenter->setResponseStatus(
                new ErrorResponse(message: _('An error occurred during session logout'), exception: $e),
            );
        }
    }
}
