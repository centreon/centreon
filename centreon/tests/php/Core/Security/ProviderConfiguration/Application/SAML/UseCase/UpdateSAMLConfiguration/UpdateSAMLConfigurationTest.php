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

namespace Tests\Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration;

use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\WriteSAMLConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration\UpdateSAMLConfiguration;
use Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration\UpdateSAMLConfigurationRequest;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;

beforeEach(function (): void {
    $this->repository = $this->createMock(WriteSAMLConfigurationRepositoryInterface::class);
    $this->contactTemplateRepository = $this->createMock(ReadContactTemplateRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
    $this->provider = $this->createMock(ProviderAuthenticationInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
});

it('should present a No Content Response when the use case is executed correctly', function (): void {
    $request = new UpdateSAMLConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->remoteLoginUrl = 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client';
    $request->entityIdUrl = 'http://127.0.0.1:4000/realms/my-realm';
    $request->publicCertificate = 'my-certificate';
    $request->logoutFrom = true;
    $request->logoutFromUrl = 'http://127.0.0.1:4000/realms/my-realm/protocol/saml';
    $request->userIdAttribute = 'email';
    $request->requestedAuthnContext = 'exact';
    $request->isAutoImportEnabled = false;
    $request->contactTemplate = null;
    $request->emailBindAttribute = null;
    $request->userNameBindAttribute = null;
    $request->authenticationConditions = [
        'is_enabled' => false,
        'attribute_path' => '',
        'authorized_values' => [],
        'trusted_client_addresses' => [],
        'blacklist_client_addresses' => [],
    ];
    $request->rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'relations' => [],
    ];
    $request->groupsMapping = [
        'is_enabled' => false,
        'attribute_path' => '',
        'relations' => [],
    ];

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $configuration = new Configuration(1, 'saml', 'saml', '{}', true, false);
    $customConfiguration = new CustomConfiguration([
        'is_active' => true,
        'is_forced' => false,
        'entity_id_url' => 'http://127.0.0.1:4000/realms/my-realm',
        'remote_login_url' => 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client',
        'certificate' => 'my-old-certificate',
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
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($configuration);

    $this->repository
        ->expects($this->once())
        ->method('updateConfiguration');

    $useCase = new UpdateSAMLConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->dataStorageEngine,
        $this->providerFactory
    );
    $presenter = new UpdateSAMLConfigurationPresenterStub($this->presenterFormatter);

    $useCase($presenter, $request);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should presenet an Error Response when an error occurs during the process', function (): void {
    $request = new UpdateSAMLConfigurationRequest();
    $request->isActive = true;
    $request->isForced = true;
    $request->remoteLoginUrl = 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client';
    $request->entityIdUrl = 'http://127.0.0.1:4000/realms/my-realm';
    $request->publicCertificate = 'my-certificate';
    $request->logoutFrom = true;
    $request->logoutFromUrl = 'http://127.0.0.1:4000/realms/my-realm/protocol/saml';
    $request->userIdAttribute = 'email';
    $request->requestedAuthnContext = 'exact';
    $request->isAutoImportEnabled = false;
    $request->contactTemplate = null;
    $request->emailBindAttribute = null;
    $request->userNameBindAttribute = null;
    $request->authenticationConditions = [
        'is_enabled' => false,
        'attribute_path' => '',
        'authorized_values' => [],
        'trusted_client_addresses' => [],
        'blacklist_client_addresses' => [],
    ];
    $request->rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'relations' => [],
    ];
    $request->groupsMapping = [
        'is_enabled' => false,
        'attribute_path' => '',
        'relations' => [],
    ];
    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->with(Provider::SAML)
        ->willThrowException(new \Exception('An error occured'));

    $useCase = new UpdateSAMLConfiguration(
        $this->repository,
        $this->contactTemplateRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->dataStorageEngine,
        $this->providerFactory
    );

    $presenter = new UpdateSAMLConfigurationPresenterStub($this->presenterFormatter);

    $useCase($presenter, $request);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
});
