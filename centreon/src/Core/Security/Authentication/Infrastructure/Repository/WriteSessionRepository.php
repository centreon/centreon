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

namespace Core\Security\Authentication\Infrastructure\Repository;

use Core\Common\Domain\Exception\RepositoryException;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Exception;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

class WriteSessionRepository implements WriteSessionRepositoryInterface
{
    /**
     * @param RequestStack $requestStack
     * @param WriteSessionTokenRepositoryInterface $writeSessionTokenRepository
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly WriteSessionTokenRepositoryInterface $writeSessionTokenRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            throw new RepositoryException(
                message: 'No session found to invalidate: ' . $e->getMessage(),
                previous: $e
            );
        }

        $sessionId = $session->getId();

        try {
            $this->writeSessionTokenRepository->deleteSession($sessionId);
        } catch (Exception $e) {
            throw new RepositoryException(
                message: 'Could not delete session token: ' . $e->getMessage(),
                context: ['session_id' => $sessionId],
                previous: $e
            );
        }

        $session->invalidate();
    }

    /**
     * @inheritDoc
     */
    public function start(\Centreon $legacySession): bool
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            throw new RepositoryException(
                message: 'No session found: ' . $e->getMessage(),
                previous: $e
            );
        }

        if ($session->isStarted()) {
            return true;
        }

        try {
            $session->start();
        } catch (\RuntimeException $e) {
            throw new RepositoryException(
                message: 'Could not start session: ' . $e->getMessage(),
                previous: $e
            );
        }

        $session->set('centreon', $legacySession);
        $session->set('isLogin', true);
        $_SESSION['centreon'] = $legacySession;

        return $session->isStarted();
    }
}
