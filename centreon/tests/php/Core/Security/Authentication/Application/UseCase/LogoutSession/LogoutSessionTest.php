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

namespace Tests\Core\Security\Authentication\Application\UseCase\LogoutSession;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSession;
use Core\Security\Authentication\Application\UseCase\LogoutSession\LogoutSessionPresenterInterface;
use Core\Security\Authentication\Infrastructure\Provider\OpenId;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration as SamlCustomConfiguration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class LogoutSessionTest extends TestCase
{
    /** @var WriteSessionRepositoryInterface&MockObject */
    private $writeSessionRepository;

    /** @var ProviderAuthenticationFactoryInterface&MockObject */
    private $providerFactory;

    /** @var RequestStack&MockObject */
    private $requestStack;

    /** @var LogoutSessionPresenterInterface&MockObject */
    private $presenter;

    protected function setUp(): void
    {
        $this->writeSessionRepository = $this->createMock(WriteSessionRepositoryInterface::class);
        $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->presenter = $this->createMock(LogoutSessionPresenterInterface::class);
    }

    public function testReturnsErrorWhenTokenIsMissing(): void
    {
        $this->presenter->expects($this->once())
            ->method('setResponseStatus')
            ->with(new ErrorResponse('No session token provided'));
        $this->writeSessionRepository->expects($this->never())->method('invalidate');
        $this->providerFactory->expects($this->never())->method('create');

        ($this->createUseCase())(null, $this->presenter);
    }

    public function testLocalUserOnlyInvalidatesSession(): void
    {
        $this->requestStack->method('getSession')->willReturn($this->createSession(Provider::LOCAL));

        $this->providerFactory->expects($this->never())->method('create');
        $this->writeSessionRepository->expects($this->once())->method('invalidate');

        ($this->createUseCase())('token', $this->presenter);
    }

    public function testSamlLogoutFromIdpIsTriggeredBeforeInvalidate(): void
    {
        $calls = [];
        $this->requestStack->method('getSession')->willReturn($this->createSession(Provider::SAML));

        $provider = $this->createSamlProvider(isActive: true, logoutFromIdp: true);
        $provider->expects($this->once())->method('logout')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'saml_logout';
            });
        $this->providerFactory->method('create')->with(Provider::SAML)->willReturn($provider);

        $this->writeSessionRepository->expects($this->once())->method('invalidate')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'invalidate';
            });

        ($this->createUseCase())('token', $this->presenter);

        // The SAML LogoutRequest reads identifiers still held in session, so it must run first.
        self::assertSame(['saml_logout', 'invalidate'], $calls);
    }

    public function testSamlLocalLogoutDoesNotTriggerProviderLogout(): void
    {
        $this->requestStack->method('getSession')->willReturn($this->createSession(Provider::SAML));

        $provider = $this->createSamlProvider(isActive: true, logoutFromIdp: false);
        $provider->expects($this->never())->method('logout');
        $this->providerFactory->method('create')->with(Provider::SAML)->willReturn($provider);

        $this->writeSessionRepository->expects($this->once())->method('invalidate');

        ($this->createUseCase())('token', $this->presenter);
    }

    public function testSamlLogoutIsSkippedWhenProviderIsInactive(): void
    {
        $this->requestStack->method('getSession')->willReturn($this->createSession(Provider::SAML));

        $provider = $this->createSamlProvider(isActive: false, logoutFromIdp: true);
        $provider->expects($this->never())->method('logout');
        $this->providerFactory->method('create')->with(Provider::SAML)->willReturn($provider);

        $this->writeSessionRepository->expects($this->once())->method('invalidate');

        ($this->createUseCase())('token', $this->presenter);
    }

    public function testOpenIdLogoutIsTriggeredAfterInvalidate(): void
    {
        $calls = [];
        $this->requestStack->method('getSession')
            ->willReturn($this->createSession(Provider::OPENID, 'id-token-xyz'));

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('isActive')->willReturn(true);
        $provider = $this->createMock(OpenId::class);
        $provider->method('getConfiguration')->willReturn($configuration);
        $provider->expects($this->once())->method('logout')
            ->with('id-token-xyz', false)
            ->willReturnCallback(static function () use (&$calls): ?string {
                $calls[] = 'openid_logout';

                return null;
            });
        $this->providerFactory->method('create')->with(Provider::OPENID)->willReturn($provider);

        $this->writeSessionRepository->expects($this->once())->method('invalidate')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'invalidate';
            });

        ($this->createUseCase())('token', $this->presenter);

        // OpenID has no /saml/sls-like callback: the local session must be wiped before redirecting.
        self::assertSame(['invalidate', 'openid_logout'], $calls);
    }

    public function testSetsErrorResponseWhenInvalidateFails(): void
    {
        $this->requestStack->method('getSession')->willReturn($this->createSession(Provider::LOCAL));

        $exception = new RepositoryException('boom');
        $this->writeSessionRepository->method('invalidate')->willThrowException($exception);

        $this->presenter->expects($this->once())
            ->method('setResponseStatus')
            ->with(new ErrorResponse('An error occurred during session logout', exception: $exception));

        ($this->createUseCase())('token', $this->presenter);
    }

    private function createUseCase(): LogoutSession
    {
        return new LogoutSession(
            $this->writeSessionRepository,
            $this->providerFactory,
            $this->requestStack,
        );
    }

    private function createSession(string $authType, string $idToken = ''): Session
    {
        $session = new Session(new MockArraySessionStorage());

        $centreon = new \stdClass();
        $centreon->user = new \stdClass();
        $centreon->user->authType = $authType;
        $session->set('centreon', $centreon);

        if ($idToken !== '') {
            $session->set('openid_id_token', $idToken);
        }

        return $session;
    }

    /**
     * @return SAML&MockObject
     */
    private function createSamlProvider(bool $isActive, bool $logoutFromIdp): SAML
    {
        $customConfiguration = (new \ReflectionClass(SamlCustomConfiguration::class))
            ->newInstanceWithoutConstructor();
        $customConfiguration->setLogoutFrom($logoutFromIdp);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('isActive')->willReturn($isActive);
        $configuration->method('getCustomConfiguration')->willReturn($customConfiguration);

        $provider = $this->createMock(SAML::class);
        $provider->method('getConfiguration')->willReturn($configuration);

        return $provider;
    }
}
