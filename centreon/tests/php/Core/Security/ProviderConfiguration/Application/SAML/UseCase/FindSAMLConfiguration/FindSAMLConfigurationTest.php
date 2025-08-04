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

namespace Tests\Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration\FindSAMLConfiguration;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\FindSAMLConfiguration\FindSAMLConfigurationResponse;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Security\Domain\Authentication\Exceptions\ProviderException;

beforeEach(function (): void {
    $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->provider = $this->createMock(ProviderAuthenticationInterface::class);
});

it('should present a SAML provider configuration', function (): void {
    $configuration = new Configuration(1, 'saml', 'saml', '{}', true, true);
    $customConfiguration = new CustomConfiguration([
        'is_active' => true,
        'is_forced' => false,
        'entity_id_url' => 'http://127.0.0.1:4000/realms/my-realm',
        'remote_login_url' => 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client',
        'certificate' => 'my-certificate',
        'logout_from' => true,
        'logout_from_url' => 'http://127.0.0.1:4000/realms/my-realm/protocol/saml',
        'user_id_attribute' => 'email',
        'requested_authn_context' => 'exact',
        'auto_import' => false,
        'contact_template' => null,
        'fullname_bind_attribute' => null,
        'email_bind_attribute' => null,
        'authentication_conditions' => new AuthenticationConditions(false, '', new Endpoint(), []),
        'roles_mapping' => new ACLConditions(false, false, '', new Endpoint(Endpoint::INTROSPECTION, ''), []),
        'groups_mapping' => new GroupsMapping(false, '', new Endpoint(), []),
    ]);
    $configuration->setCustomConfiguration($customConfiguration);

    $this->provider
        ->expects($this->any())
        ->method('getConfiguration')
        ->willReturn($configuration);

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->with(Provider::SAML)
        ->willReturn($this->provider);

    $useCase = new FindSAMLConfiguration($this->providerFactory);
    $presenter = new FindSAMLConfigurationPresenterStub($this->presenterFormatter);

    $useCase($presenter);

    expect($presenter->response)->toBeInstanceOf(FindSAMLConfigurationResponse::class);
    expect($presenter->response->isActive)->toBeTrue();
    expect($presenter->response->isForced)->toBeTrue();
    expect($presenter->response->entityIdUrl)->toBe($customConfiguration->getEntityIDUrl());
    expect($presenter->response->remoteLoginUrl)->toBe($customConfiguration->getRemoteLoginUrl());
    expect($presenter->response->logoutFrom)->toBe($customConfiguration->getLogoutFrom());
    expect($presenter->response->logoutFromUrl)->toBe($customConfiguration->getLogoutFromUrl());
    expect($presenter->response->userIdAttribute)->toBe($customConfiguration->getUserIdAttribute());
    expect($presenter->response->requestedAuthnContext)
        ->toBe($customConfiguration->getRequestedAuthnContext()->toString());
});

it('should present an ErrorResponse when an error occurs during the process', function (): void {
    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->with(Provider::SAML)
        ->willThrowException(ProviderException::providerConfigurationNotFound(Provider::SAML));

    $useCase = new FindSAMLConfiguration($this->providerFactory);
    $presenter = new FindSAMLConfigurationPresenterStub($this->presenterFormatter);

    $useCase($presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe('Provider configuration (saml) not found');
});
