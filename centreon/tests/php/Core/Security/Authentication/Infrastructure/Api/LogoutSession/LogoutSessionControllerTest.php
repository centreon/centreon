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

namespace Tests\Core\Security\Authentication\Infrastructure\Api\LogoutSession;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Infrastructure\Common\Presenter\JsonFormatter;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSession;
use Core\Security\Authentication\Infrastructure\Api\LogoutSession\LogoutSessionController;
use Core\Security\Authentication\Infrastructure\Api\LogoutSession\LogoutSessionPresenter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LogoutSessionControllerTest extends TestCase
{
    private RequestStack&MockObject $requestStack;

    private LogoutSession&MockObject $useCase;

    private LogoutSessionPresenter $logoutSessionPresenter;

    public function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(Request::create('http://localhost/'));
        $this->useCase = $this->createMock(LogoutSession::class);
        $this->logoutSessionPresenter = new LogoutSessionPresenter(new JsonFormatter());
    }

    /**
     * test Logout.
     */
    public function testLogout(): void
    {
        $logoutSessionController = new LogoutSessionController();
        $logoutSessionController->setHttpServerBag($this->requestStack);

        $request = new Request([], [], [], [session_name() => 'token']);

        $this->logoutSessionPresenter->setResponseStatus(new NoContentResponse());

        $this->useCase->expects($this->once())
            ->method('__invoke')
            ->with('token', $this->logoutSessionPresenter);

        $response = $logoutSessionController($this->useCase, $request, $this->logoutSessionPresenter);

        $this->assertEquals('http://localhost/login', $response->headers->get('location'));
    }

    /**
     * test Logout with bad token.
     */
    public function testLogoutFailed(): void
    {
        $logoutSessionController = new LogoutSessionController();
        $logoutSessionController->setHttpServerBag($this->requestStack);

        $request = new Request();

        $this->logoutSessionPresenter->setResponseStatus(new ErrorResponse('No session token provided'));

        $this->useCase->expects($this->once())
            ->method('__invoke')
            ->with(null, $this->logoutSessionPresenter);

        $response = $logoutSessionController($this->useCase, $request, $this->logoutSessionPresenter);

        $this->assertEquals('http://localhost/login', $response->headers->get('location'));
    }
}
