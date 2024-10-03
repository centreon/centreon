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

namespace Tests\Core\Security\ProviderConfiguration\Application\OpenId\UseCase\PartialUpdateOpenIdConfiguration;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\ReadOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\OpenId\UseCase\PartialUpdateOpenIdConfiguration\{
    PartialUpdateOpenIdConfiguration,
    PartialUpdateOpenIdConfigurationPresenterInterface,
    PartialUpdateOpenIdConfigurationRequest
};
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;

beforeEach(function (): void {
    $this->repository = $this->createMock(WriteOpenIdConfigurationRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenter = $this->createMock(PartialUpdateOpenIdConfigurationPresenterInterface::class);
    $this->readOpenIdRepository = $this->createMock(ReadOpenIdConfigurationRepositoryInterface::class);
    $this->contactTemplateRepository = $this->createMock(ReadContactTemplateRepositoryInterface::class);
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $this->contactGroup = new ContactGroup(1, 'contact_group');
    $this->contactTemplate = new ContactTemplate(1, 'contact_template');
    $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
    $this->provider = $this->createMock(ProviderAuthenticationInterface::class);
    $this->configuration = $this->createMock(Configuration::class);
    $this->customConfig = $this->createMock(CustomConfiguration::class);
    $this->customConfigArray = [
        'is_active' => false,
        'is_forced' => false,
        'base_url' => null,
        'authorization_endpoint' => null,
        'token_endpoint' => null,
        'introspection_token_endpoint' => null,
        'userinfo_endpoint' => null,
        'endsession_endpoint' => null,
        'connection_scopes' => [],
        'login_claim' => null,
        'client_id' => null,
        'client_secret' => null,
        'authentication_type' => 'client_secret_post',
        'verify_peer' => true,
        'auto_import' => false,
        'contact_template' => null,
        'email_bind_attribute' => null,
        'fullname_bind_attribute' => null,
        'roles_mapping' => [
            'is_enabled' => false,
            'apply_only_first_role' => false,
            'attribute_path' => '',
            'endpoint' => [
                'type' => 'introspection_endpoint',
                'custom_endpoint' => '',
            ],
            'relations' => [],
        ],
        'authentication_conditions' => [
            'is_enabled' => false,
            'attribute_path' => '',
            'endpoint' => [
                'type' => 'introspection_endpoint',
                'custom_endpoint' => null,
            ],
            'authorized_values' => [],
            'trusted_client_addresses' => [],
            'blacklist_client_addresses' => [],
        ],
        'groups_mapping' => [
            'is_enabled' => false,
            'attribute_path' => '',
            'endpoint' => [
                'type' => 'introspection_endpoint',
                'custom_endpoint' => null,
            ],
            'relations' => [],
        ],
        'redirect_url' => null,
    ];
});

it('should present a NoContentResponse when the use case is executed correctly', function (): void {
    $request = new PartialUpdateOpenIdConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->baseUrl = 'http://127.0.0.1/auth/openid-connect';
    $request->authorizationEndpoint = '/authorization';
    $request->tokenEndpoint = '/token';
    $request->introspectionTokenEndpoint = '/introspect';
    $request->userInformationEndpoint = '/userinfo';
    $request->endSessionEndpoint = '/logout';
    $request->connectionScopes = [];
    $request->loginClaim = 'preferred_username';
    $request->clientId = 'MyCl1ientId';
    $request->clientSecret = 'MyCl1ientSuperSecr3tKey';
    $request->authenticationType = 'client_secret_post';
    $request->verifyPeer = false;
    $request->isAutoImportEnabled = false;
    $request->contactTemplate = ['id' => 1];
    $request->rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => '',
        ],
        'relations' => [],
    ];

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $this->provider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configuration);

    $this->configuration
        ->expects($this->once())
        ->method('getCustomConfiguration')
        ->willReturn($this->customConfig);

    $this->customConfig
        ->expects($this->once())
        ->method('toArray')
        ->willReturn($this->customConfigArray);

    $this->contactTemplateRepository
        ->expects($this->once())
        ->method('find')
        ->with(1)
        ->willReturn($this->contactTemplate);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new NoContentResponse());

    $useCase = new PartialUpdateOpenIdConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->providerFactory,
        $this->readVaultConfigurationRepository,
        $this->writeVaultRepository
    );
    $useCase($this->presenter, $request);
});

it('should present an ErrorResponse when an error occured during the use case execution', function (): void {
    $request = new PartialUpdateOpenIdConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->baseUrl = 'http://127.0.0.1/auth/openid-connect';
    $request->authorizationEndpoint = '/authorization';
    $request->tokenEndpoint = '/token';
    $request->introspectionTokenEndpoint = '/introspect';
    $request->userInformationEndpoint = '/userinfo';
    $request->endSessionEndpoint = '/logout';
    $request->connectionScopes = [];
    $request->loginClaim = 'preferred_username';
    $request->clientId = 'MyCl1ientId';
    $request->clientSecret = 'MyCl1ientSuperSecr3tKey';
    $request->authenticationType = 'client_secret_post';
    $request->verifyPeer = false;
    $request->isAutoImportEnabled = false;
    $request->contactTemplate = ['id' => 1];
    $request->rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => '',
        ],
        'relations' => [],
    ];
    $request->authenticationConditions = [
        'is_enabled' => true,
        'attribute_path' => 'info.groups',
        'endpoint' => ['type' => 'introspection_endpoint', 'custom_endpoint' => null],
        'authorized_values' => ['groupsA'],
        'trusted_client_addresses' => ['abcd_.@'],
        'blacklist_client_addresses' => [],
    ];

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $this->provider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configuration);

    $this->configuration
        ->expects($this->once())
        ->method('getCustomConfiguration')
        ->willReturn($this->customConfig);

    $this->customConfig
        ->expects($this->once())
        ->method('toArray')
        ->willReturn($this->customConfigArray);

    $this->contactTemplateRepository
        ->expects($this->once())
        ->method('find')
        ->with(1)
        ->willReturn($this->contactTemplate);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse(
            AssertionException::ipOrDomain('abcd_.@', 'AuthenticationConditions::trustedClientAddresses')->getMessage()
        ));

    $useCase = new PartialUpdateOpenIdConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->providerFactory,
        $this->readVaultConfigurationRepository,
        $this->writeVaultRepository
    );

    $useCase($this->presenter, $request);
});

it('should present an Error Response when auto import is enable and mandatory parameters are missing', function (): void {
    $request = new PartialUpdateOpenIdConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->baseUrl = 'http://127.0.0.1/auth/openid-connect2';
    $request->authorizationEndpoint = '/authorization';
    $request->tokenEndpoint = '/token';
    $request->introspectionTokenEndpoint = '/introspect';
    $request->userInformationEndpoint = '/userinfo';
    $request->endSessionEndpoint = '/logout';
    $request->connectionScopes = [];
    $request->loginClaim = 'preferred_username';
    $request->clientId = 'MyCl1ientId';
    $request->clientSecret = 'MyCl1ientSuperSecr3tKey';
    $request->authenticationType = 'client_secret_post';
    $request->verifyPeer = false;
    $request->isAutoImportEnabled = true;
    $request->rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => '',
        ],
        'relations' => [],
    ];

    $missingParameters = [
        'contact_template',
        'email_bind_attribute',
        'fullname_bind_attribute',
    ];

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $this->provider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configuration);

    $this->configuration
        ->expects($this->once())
        ->method('getCustomConfiguration')
        ->willReturn($this->customConfig);

    $this->customConfig
        ->expects($this->once())
        ->method('toArray')
        ->willReturn($this->customConfigArray);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse(
            ConfigurationException::missingAutoImportMandatoryParameters($missingParameters)->getMessage()
        ));

    $useCase = new PartialUpdateOpenIdConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->providerFactory,
        $this->readVaultConfigurationRepository,
        $this->writeVaultRepository
    );

    $useCase($this->presenter, $request);
});

it('should present an Error Response when auto import is enable and the contact template doesn\'t exist', function (): void {
    $request = new PartialUpdateOpenIdConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->baseUrl = 'http://127.0.0.1/auth/openid-connect';
    $request->authorizationEndpoint = '/authorization';
    $request->tokenEndpoint = '/token';
    $request->introspectionTokenEndpoint = '/introspect';
    $request->userInformationEndpoint = '/userinfo';
    $request->endSessionEndpoint = '/logout';
    $request->connectionScopes = [];
    $request->loginClaim = 'preferred_username';
    $request->clientId = 'MyCl1ientId';
    $request->clientSecret = 'MyCl1ientSuperSecr3tKey';
    $request->authenticationType = 'client_secret_post';
    $request->verifyPeer = false;
    $request->isAutoImportEnabled = true;
    $request->contactTemplate = ['id' => 1, 'name' => 'contact_template'];
    $request->emailBindAttribute = 'email';
    $request->userNameBindAttribute = 'name';

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $this->provider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configuration);

    $this->configuration
        ->expects($this->once())
        ->method('getCustomConfiguration')
        ->willReturn($this->customConfig);

    $this->customConfig
        ->expects($this->once())
        ->method('toArray')
        ->willReturn($this->customConfigArray);

    $this->contactTemplateRepository
        ->expects($this->once())
        ->method('find')
        ->with($request->contactTemplate['id'])
        ->willReturn(null);

    $this->presenter
        ->expects($this->once())
        ->method('setResponseStatus')
        ->with(new ErrorResponse(
            ConfigurationException::contactTemplateNotFound($request->contactTemplate['name'])->getMessage()
        ));

    $useCase = new PartialUpdateOpenIdConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->providerFactory,
        $this->readVaultConfigurationRepository,
        $this->writeVaultRepository
    );

    $useCase($this->presenter, $request);
});
