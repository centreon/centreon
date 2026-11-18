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

namespace Tests\Core\Security\Authentication\Infrastructure\Provider;

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Core\Application\Configuration\User\Repository\WriteUserRepositoryInterface;
use Core\Security\Authentication\Application\Repository\WriteSessionRepositoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\SAML;
use Core\Security\Authentication\Infrastructure\Provider\SamlAuthFactoryInterface;
use Core\Security\Authentication\Infrastructure\Provider\Settings\Formatter\SettingsFormatterInterface;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\Conditions;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\RolesMapping;
use OneLogin\Saml2\Auth;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class SAMLTest extends TestCase
{
    /** @var SettingsFormatterInterface&MockObject */
    private $formatter;

    /** @var WriteSessionRepositoryInterface&MockObject */
    private $writeSessionRepository;

    /** @var SamlAuthFactoryInterface&MockObject */
    private $authFactory;

    /** @var Auth&MockObject */
    private $auth;

    private SAML $saml;

    protected function setUp(): void
    {
        $this->formatter = $this->createMock(SettingsFormatterInterface::class);
        $this->formatter->method('format')->willReturn([]);

        $this->writeSessionRepository = $this->createMock(WriteSessionRepositoryInterface::class);
        $this->authFactory = $this->createMock(SamlAuthFactoryInterface::class);
        $this->auth = $this->createMock(Auth::class);
        $this->authFactory->method('create')->willReturn($this->auth);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomConfiguration')
            ->willReturn($this->createMock(CustomConfigurationInterface::class));

        $this->saml = new SAML(
            $this->createMock(Container::class),
            $this->createMock(ContactRepositoryInterface::class),
            $this->createMock(LoginLoggerInterface::class),
            $this->createMock(WriteUserRepositoryInterface::class),
            $this->createMock(Conditions::class),
            $this->createMock(RolesMapping::class),
            $this->createMock(GroupsMapping::class),
            $this->formatter,
            $this->writeSessionRepository,
            $this->authFactory,
        );
        $this->saml->setConfiguration($configuration);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['saml'], $_SESSION['LogoutRequestID']);
    }

    public function testLogoutForwardsSamlIdentifiersFromSession(): void
    {
        $_SESSION['saml'] = [
            'samlNameId' => 'name-id',
            'samlSessionIndex' => 'session-index',
            'samlNameIdFormat' => 'name-id-format',
            'samlNameIdNameQualifier' => 'name-qualifier',
            'samlNameIdSPNameQualifier' => 'sp-name-qualifier',
        ];

        $captured = [];
        $this->auth->expects($this->once())->method('logout')
            ->willReturnCallback(static function (
                $returnTo = null,
                array $parameters = [],
                $nameId = null,
                $sessionIndex = null,
                $stay = false,
                $nameIdFormat = null,
                $nameIdNameQualifier = null,
                $nameIdSPNameQualifier = null,
            ) use (&$captured): ?string {
                $captured = [
                    'returnTo' => $returnTo,
                    'nameId' => $nameId,
                    'sessionIndex' => $sessionIndex,
                    'nameIdFormat' => $nameIdFormat,
                    'nameIdNameQualifier' => $nameIdNameQualifier,
                    'nameIdSPNameQualifier' => $nameIdSPNameQualifier,
                ];

                return null;
            });

        $this->saml->logout();

        self::assertSame([
            'returnTo' => '/login',
            'nameId' => 'name-id',
            'sessionIndex' => 'session-index',
            'nameIdFormat' => 'name-id-format',
            'nameIdNameQualifier' => 'name-qualifier',
            'nameIdSPNameQualifier' => 'sp-name-qualifier',
        ], $captured);
    }

    public function testHandleCallbackLogoutResponseInvalidatesLocalSession(): void
    {
        $_SESSION['LogoutRequestID'] = 'request-id';

        // Emulate processSLO: with keepLocalSession=false it must invoke the delete-session callback.
        $this->auth->expects($this->once())->method('processSLO')
            ->willReturnCallback(static function (
                bool $keepLocalSession = false,
                $requestId = null,
                bool $retrieveParametersFromServer = false,
                ?callable $cbDeleteSession = null,
                bool $stay = false,
            ): void {
                if ($keepLocalSession === false && is_callable($cbDeleteSession)) {
                    $cbDeleteSession();
                }
            });

        $this->writeSessionRepository->expects($this->once())->method('invalidate');

        $this->saml->handleCallbackLogoutResponse();
    }
}
