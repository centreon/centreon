<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Application\Security\UseCase\LogoutSession;

use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\ReadTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSession;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSessionPresenterInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionTokenRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteTokenRepositoryInterface;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

class LogoutSessionTest extends TestCase
{
    /**
     * @var WriteSessionTokenRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $writeSessionTokenRepository;

    /**
     * @var WriteSessionRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $writeSessionRepository;

    /**
     * @var WriteTokenRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $writeTokenRepository;

    /**
     * @var LogoutSessionPresenterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $logoutSessionPresenter;

    /**
     * @var ReadTokenRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private ReadTokenRepositoryInterface $readTokenRepository;

    /**
     * @var ProviderAuthenticationFactoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private ProviderAuthenticationFactoryInterface $providerFactory;

    /**
     * @var RequestStack&\PHPUnit\Framework\MockObject\MockObject
     */
    private RequestStack $requestStack;

    public function setUp(): void
    {
        $this->writeSessionTokenRepository = $this->createMock(WriteSessionTokenRepositoryInterface::class);
        $this->writeSessionRepository = $this->createMock(WriteSessionRepositoryInterface::class);
        $this->writeTokenRepository = $this->createMock(WriteTokenRepositoryInterface::class);
        $this->logoutSessionPresenter = $this->createMock(LogoutSessionPresenterInterface::class);
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class);
        $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
    }

    /**
     * test Logout.
     */
    public function testLogout(): void
    {
        $logoutSession = new LogoutSession(
            $this->writeSessionTokenRepository,
            $this->writeSessionRepository,
            $this->writeTokenRepository,
            $this->readTokenRepository,
            $this->providerFactory,
            $this->requestStack
        );

        $session = new Session();
        $session->setId('session_abcd');
        $this->requestStack
            ->expects($this->any())
            ->method('getSession')
            ->willReturn($session);

        $this->writeTokenRepository->expects($this->once())
            ->method('deleteExpiredSecurityTokens');

        $this->writeSessionTokenRepository->expects($this->once())
            ->method('deleteSession')
            ->with('session_abcd');

        $this->writeSessionRepository->expects($this->once())
            ->method('invalidate');

        $logoutSession('token', $this->logoutSessionPresenter);
    }

    /**
     * test Logout with bad token.
     */
    public function testLogoutFailed(): void
    {
        $logoutSession = new LogoutSession(
            $this->writeSessionTokenRepository,
            $this->writeSessionRepository,
            $this->writeTokenRepository,
            $this->readTokenRepository,
            $this->providerFactory,
            $this->requestStack
        );

        $this->logoutSessionPresenter->expects($this->once())
            ->method('setResponseStatus')
            ->with(new ErrorResponse('No session token provided'));

        $logoutSession(null, $this->logoutSessionPresenter);
    }
}
