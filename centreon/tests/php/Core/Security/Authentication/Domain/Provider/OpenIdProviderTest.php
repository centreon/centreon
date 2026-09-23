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

namespace Tests\Core\Security\Authentication\Domain\Provider;

use Adaptation\Log\LoggerAuthentication;
use Centreon\Domain\Contact\Interfaces\ContactServiceInterface;
use Core\Application\Configuration\User\Repository\WriteUserRepositoryInterface;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use Core\Security\Authentication\Domain\Provider\OpenIdProvider;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath\AttributePathFetcher;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\Conditions;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\GroupsMapping as GroupsMappingSecurityAccess;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\RolesMapping;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

beforeEach(function (): void {
    $this->provider = new OpenIdProvider(
        $this->createMock(HttpClientInterface::class),
        $this->createMock(UrlGeneratorInterface::class),
        $this->createMock(ContactServiceInterface::class),
        $this->createMock(WriteUserRepositoryInterface::class),
        $this->createMock(Conditions::class),
        $this->createMock(RolesMapping::class),
        $this->createMock(GroupsMappingSecurityAccess::class),
        $this->createMock(AttributePathFetcher::class),
        $this->createMock(ReadVaultRepositoryInterface::class),
        false,
    );

    $authLoggerReflection = new \ReflectionClass(LoggerAuthentication::class);
    $facade = $authLoggerReflection->newInstanceWithoutConstructor();
    $spy = new class () extends \Psr\Log\AbstractLogger {
        public function log($level, string|\Stringable $message, array $context = []): void
        {
        }
    };
    $authLoggerReflection->getProperty('logger')->setValue($facade, $spy);
    $authLoggerReflection->getProperty('instance')->setValue(null, $facade);

    $this->callVerify = function (
        string $clientIp,
        array $trustedAddresses = [],
        array $blacklistAddresses = [],
    ): void {
        $authConditions = new AuthenticationConditions(false, '', null, []);
        $authRef = new \ReflectionClass($authConditions);
        $authRef->getProperty('trustedClientAddresses')->setValue($authConditions, $trustedAddresses);
        $authRef->getProperty('blacklistClientAddresses')->setValue($authConditions, $blacklistAddresses);

        $customConfiguration = $this->createMock(CustomConfiguration::class);
        $customConfiguration->method('getAuthenticationConditions')->willReturn($authConditions);

        $aclConditions = $this->createMock(ACLConditions::class);
        $aclConditions->method('isEnabled')->willReturn(false);
        $customConfiguration->method('getACLConditions')->willReturn($aclConditions);

        $groupsMapping = $this->createMock(GroupsMapping::class);
        $groupsMapping->method('isEnabled')->willReturn(false);
        $customConfiguration->method('getGroupsMapping')->willReturn($groupsMapping);

        $configuration = new Configuration(1, 'openid', 'OpenID', '{}', true, false);
        $configuration->setCustomConfiguration($customConfiguration);

        $providerRef = new \ReflectionClass($this->provider);
        $providerRef->getProperty('configuration')->setValue($this->provider, $configuration);
        $providerRef->getProperty('providerToken')->setValue(
            $this->provider,
            new NewProviderToken('fake-token', new \DateTimeImmutable()),
        );
        $providerRef->getProperty('idTokenPayload')->setValue($this->provider, []);

        $providerRef->getMethod('verifyThatClientIsAllowedToConnectOrFail')
            ->invoke($this->provider, $clientIp);
    };
});

afterEach(function (): void {
    (new \ReflectionClass(LoggerAuthentication::class))->getProperty('instance')->setValue(null, null);
});

it('allows a client whose IP matches a trusted address', function (): void {
    ($this->callVerify)('192.168.1.10', ['192\\.168\\.1\\.10']);
    expect(true)->toBeTrue();
});

it('allows a client whose IP matches one of several trusted addresses', function (): void {
    ($this->callVerify)('10.0.0.5', ['192\\.168\\.1\\.1', '10\\.0\\.0\\.\\d+']);
    expect(true)->toBeTrue();
});

it('rejects a client whose IP matches none of the trusted addresses', function (): void {
    ($this->callVerify)('172.16.0.1', ['192\\.168\\.1\\.1', '10\\.0\\.0\\.\\d+']);
})->throws(SSOAuthenticationException::class, 'Your IP is not whitelisted');

it('skips the trusted filter when the trusted address list is empty', function (): void {
    ($this->callVerify)('172.16.0.1');
    expect(true)->toBeTrue();
});

it('skips the trusted filter when all trusted addresses are empty strings', function (): void {
    ($this->callVerify)('172.16.0.1', ['', '']);
    expect(true)->toBeTrue();
});

it('filters out empty strings and still matches the remaining trusted address', function (): void {
    ($this->callVerify)('192.168.1.10', ['', '192\\.168\\.1\\.10', '']);
    expect(true)->toBeTrue();
});

it('rejects a client whose IP is blacklisted', function (): void {
    ($this->callVerify)('192.168.1.10', [], ['192\\.168\\.1\\.10']);
})->throws(SSOAuthenticationException::class, 'Your IP is blacklisted');

it('rejects a blacklisted IP even when it is also trusted', function (): void {
    ($this->callVerify)('192.168.1.10', ['192\\.168\\.1\\.10'], ['192\\.168\\.1\\.10']);
})->throws(SSOAuthenticationException::class, 'Your IP is blacklisted');

it('allows a client not on the blacklist when no trusted list is configured', function (): void {
    ($this->callVerify)('10.0.0.5', [], ['192\\.168\\.1\\.1']);
    expect(true)->toBeTrue();
});
