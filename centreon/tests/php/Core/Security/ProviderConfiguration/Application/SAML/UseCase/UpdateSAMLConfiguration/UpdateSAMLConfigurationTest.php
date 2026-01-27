<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Adaptation\Database\Connection\ConnectionInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Infrastructure\Repository\DatabaseRepositoryManager;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
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
use Mockery;

beforeEach(function (): void {
    $this->writeSAMLConfigurationRepository = $this->createMock(WriteSAMLConfigurationRepositoryInterface::class);
    $this->readContactTemplateRepository = $this->createMock(ReadContactTemplateRepositoryInterface::class);
    $this->readContactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection
        ->shouldReceive('isTransactionActive')
        ->andReturn(false);
    $connection
        ->shouldReceive('startTransaction')
        ->andReturnNull();
    $connection
        ->shouldReceive('commitTransaction')
        ->andReturnTrue();
    $this->databaseRepositoryManager = new DatabaseRepositoryManager($connection);
    $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class);
    $this->provider = $this->createMock(ProviderAuthenticationInterface::class);
    $this->presenter = new UpdateSAMLConfigurationPresenterStub();
});

it('should present a No Content Response when the use case is executed correctly', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: true,
        isForced: true,
        remoteLoginUrl: 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client',
        entityIdUrl: 'http://127.0.0.1:4000/realms/my-realm',
        publicCertificate: 'my-certificate',
        userIdAttribute: 'email',
        requestedAuthnContext: false,
        logoutFrom: true,
        logoutFromUrl: 'http://127.0.0.1:4000/realms/my-realm/protocol/saml',
        isAutoImportEnabled: false,
        rolesMapping: [
            'is_enabled' => false,
            'apply_only_first_role' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
        authenticationConditions: [
            'is_enabled' => false,
            'attribute_path' => '',
            'authorized_values' => [],
            'trusted_client_addresses' => [],
            'blacklist_client_addresses' => [],
        ],
        groupsMapping: [
            'is_enabled' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
    );

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->provider);

    $configuration = new Configuration(1, 'saml', 'saml', '{}', true, false);
    $customConfiguration = CustomConfiguration::createFromValues([
        'is_active' => true,
        'is_forced' => false,
        'entity_id_url' => 'http://127.0.0.1:4000/realms/my-realm',
        'remote_login_url' => 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client',
        'certificate' => 'my-old-certificate',
        'logout_from' => true,
        'logout_from_url' => 'http://127.0.0.1:4000/realms/my-realm/protocol/saml',
        'user_id_attribute' => 'email',
        'requested_authn_context' => false,
        'requested_authn_context_comparison' => 'exact',
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

    $this->writeSAMLConfigurationRepository
        ->expects($this->once())
        ->method('updateConfiguration');

    $useCase = new UpdateSAMLConfiguration(
        $this->writeSAMLConfigurationRepository,
        $this->readContactTemplateRepository,
        $this->readContactGroupRepository,
        $this->readAccessGroupRepository,
        $this->databaseRepositoryManager,
        $this->providerFactory
    );

    $useCase($this->presenter, $request);

    expect($this->presenter->response)->toBeInstanceOf(NoContentResponse::class)
        ->and($this->presenter->response->getMessage())->toBe('No content');
});

it('should present an Error Response when an error occurs during the process', function (): void {
    $request = new UpdateSAMLConfigurationRequest(
        isActive: true,
        isForced: true,
        remoteLoginUrl: 'http://127.0.0.1:4000/realms/my-realm/protocol/saml/clients/my-client',
        entityIdUrl: 'http://127.0.0.1:4000/realms/my-realm',
        publicCertificate: 'my-certificate',
        userIdAttribute: 'email',
        requestedAuthnContext: false,
        logoutFrom: true,
        logoutFromUrl: 'http://127.0.0.1:4000/realms/my-realm/protocol/saml',
        isAutoImportEnabled: false,
        rolesMapping: [
            'is_enabled' => false,
            'apply_only_first_role' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
        authenticationConditions: [
            'is_enabled' => false,
            'attribute_path' => '',
            'authorized_values' => [],
            'trusted_client_addresses' => [],
            'blacklist_client_addresses' => [],
        ],
        groupsMapping: [
            'is_enabled' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
    );

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->with(Provider::SAML)
        ->willThrowException(new \Exception('An error occured'));

    $useCase = new UpdateSAMLConfiguration(
        $this->writeSAMLConfigurationRepository,
        $this->readContactTemplateRepository,
        $this->readContactGroupRepository,
        $this->readAccessGroupRepository,
        $this->databaseRepositoryManager,
        $this->providerFactory
    );

    $useCase($this->presenter, $request);

    expect($this->presenter->response)->toBeInstanceOf(ErrorResponse::class);
});
