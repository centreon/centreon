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

namespace Tests\Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type as AccType;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\Type as BrokerIOType;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\Migrator\VmWareV6CredentialMigrator;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use DateTimeImmutable;
use Utility\UUIDGenerator;

beforeEach(function (): void {
    $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);
    $this->writePollerMacroRepository = $this->createMock(WritePollerMacroRepositoryInterface::class);
    $this->writeOptionRepository = $this->createMock(WriteOptionRepositoryInterface::class);
    $this->writeOpenIdConfigurationRepository = $this->createMock(WriteOpenIdConfigurationRepositoryInterface::class);
    $this->readBrokerInputOutputRepository = $this->createMock(ReadBrokerInputOutputRepositoryInterface::class);
    $this->writeBrokerInputOutputRepository = $this->createMock(WriteBrokerInputOutputRepositoryInterface::class);
    $this->writeAccRepository = $this->createMock(WriteAccRepositoryInterface::class);
    $this->accCredentialMigrators = [$this->createMock(VmWareV6CredentialMigrator::class)];

    $this->credential1 = new CredentialDto();
    $this->credential1->resourceId = 1;
    $this->credential1->type = CredentialTypeEnum::TYPE_HOST;
    $this->credential1->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
    $this->credential1->value = 'community';

    $this->credential2 = new CredentialDto();
    $this->credential2->resourceId = 2;
    $this->credential2->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    $this->credential2->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
    $this->credential2->value = 'community';

    $this->credential3 = new CredentialDto();
    $this->credential3->resourceId = 3;
    $this->credential3->type = CredentialTypeEnum::TYPE_SERVICE;
    $this->credential3->name = 'MACRO';
    $this->credential3->value = 'macro';

    $this->credential4 = new CredentialDto();
    $this->credential4->resourceId = 4;
    $this->credential4->type = CredentialTypeEnum::TYPE_POLLER_MACRO;
    $this->credential4->name = '$POLLERMACRO$';
    $this->credential4->value = 'value';

    $this->credential5 = new CredentialDto();
    $this->credential5->resourceId = 5;
    $this->credential5->type = CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT;
    $this->credential5->name = 'my-output_db_password';
    $this->credential5->value = 'my-password';

    $this->hosts = [
        new Host(1, 1, 'Host1', '127.0.0.1'),
    ];

    $this->hostTemplates = [
        new HostTemplate(2, 'HostTemplate1', 'HostTemplate1'),
    ];

    $this->hostMacro = new Macro(1,'_MACRO_HOST1','value');
    $this->hostMacro->setIsPassword(true);
    $this->hostMacros = [$this->hostMacro];

    $this->serviceMacro = new Macro(1,'_MACRO_SERVICE1','value');
    $this->serviceMacro->setIsPassword(true);
    $this->serviceMacros = [$this->serviceMacro];

    $this->pollerMacro = new PollerMacro(1, '$POLLERMACRO$', 'value', null, true, true);
    $this->pollerMacros = [$this->pollerMacro];

    $customConfiguration = new CustomConfiguration([
        'is_active' => true,
        'client_id' => 'MyCl1ientId',
        'client_secret' => 'MyCl1ientSuperSecr3tKey',
        'base_url' => 'http://127.0.0.1/auth/openid-connect',
        'auto_import' => false,
        'authorization_endpoint' => '/authorization',
        'token_endpoint' => '/token',
        'introspection_token_endpoint' => '/introspect',
        'userinfo_endpoint' => '/userinfo',
        'contact_template' => new ContactTemplate(1, 'contact_template'),
        'email_bind_attribute' => null,
        'fullname_bind_attribute' => null,
        'endsession_endpoint' => '/logout',
        'connection_scopes' => [],
        'login_claim' => 'preferred_username',
        'authentication_type' => 'client_secret_post',
        'verify_peer' => false,
        'claim_name' => 'groups',
        'roles_mapping' => new ACLConditions(
            false,
            false,
            '',
            new Endpoint(Endpoint::INTROSPECTION, ''),
            []
        ),
        'authentication_conditions' => new AuthenticationConditions(false, '', new Endpoint(), []),
        'groups_mapping' => new GroupsMapping(false, '', new Endpoint(), []),
        'redirect_url' => null,
    ]);
    $this->openIdProviderConfiguration = new Configuration(1,
        type: Provider::OPENID,
        name: Provider::OPENID,
        jsonCustomConfiguration: '{}',
        isActive: true,
        isForced: false
    );
    $this->openIdProviderConfiguration->setCustomConfiguration($customConfiguration);

    $this->brokerInputOutputs = new BrokerInputOutput(
        id: 0,
        tag: 'output',
        type: new BrokerIOType(29, 'Database configuration writer'),
        name: 'my-output',
        parameters: [
            'db_type' => 'db2',
            'db_host' => 'localhost',
            'db_port' => 8080,
            'db_user' => 'admin',
            'db_password' => 'my-password',
            'db_name' => 'centreon',
        ]
    );

    $this->acc = new Acc(
        id: 1,
        name: 'my-ACC',
        type: AccType::VMWARE_V6,
        createdBy: 1,
        updatedBy: 1,
        createdAt: new DateTimeImmutable(),
        updatedAt: new DateTimeImmutable(),
        parameters: $this->createMock(AccParametersInterface::class)
    );
});

it('tests getIterator method with hosts, hostTemplates and service macros', function (): void {
    $credentials = new \ArrayIterator([$this->credential1, $this->credential2, $this->credential3]);
    $uuid = (new UUIDGenerator())->generateV4();
    $this->writeVaultRepository->method('upsert')->willReturnOnConsecutiveCalls(
        [$this->credential1->name => 'secret::hashicorp_vault::vault/path/' . $uuid . '::' .  $this->credential1->name],
        [$this->credential2->name => 'secret::hashicorp_vault::vault/path/' . $uuid . '::' .  $this->credential2->name],
        ['_SERVICE' . $this->credential3->name => 'secret::hashicorp_vault::vault/path/' . $uuid . '::' . '_SERVICE' . $this->credential3->name],
    );

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        readBrokerInputOutputRepository: $this->readBrokerInputOutputRepository,
        writeBrokerInputOutputRepository: $this->writeBrokerInputOutputRepository,
        writeAccRepository: $this->writeAccRepository,
        accCredentialMigrators: $this->accCredentialMigrators,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration,
        brokerInputOutputs: [5 => [$this->brokerInputOutputs]],
        accs: [$this->acc],
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($uuid);
        expect($status->resourceId)->toBeIn([1, 2, 3]);
        expect($status->vaultPath)->toBe(
            'secret::hashicorp_vault::vault/path/' . $uuid . '::'
            . ($status->type === CredentialTypeEnum::TYPE_SERVICE
                ? '_SERVICE' . $status->credentialName
                : $status->credentialName)
        );
        expect($status->type)->toBeIn([CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE, CredentialTypeEnum::TYPE_SERVICE]);
        expect($status->credentialName)->toBeIn([VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, 'MACRO']);
    }
});

it('tests getIterator method with poller macros', function (): void {
    $credentials = new \ArrayIterator([$this->credential4]);
    $uuid = (new UUIDGenerator())->generateV4();
    $this->writeVaultRepository->method('upsert')->willReturn(
        [$this->credential4->name => 'secret::hashicorp_vault::vault/path/' . $uuid . '::' .  $this->credential4->name]
    );

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        readBrokerInputOutputRepository: $this->readBrokerInputOutputRepository,
        writeBrokerInputOutputRepository: $this->writeBrokerInputOutputRepository,
        writeAccRepository: $this->writeAccRepository,
        accCredentialMigrators: $this->accCredentialMigrators,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration,
        brokerInputOutputs: [5 => [$this->brokerInputOutputs]],
        accs: [$this->acc],
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($uuid);
        expect($status->resourceId)->toBeIn([4]);
        expect($status->vaultPath)->toBe('secret::hashicorp_vault::vault/path/'
            . $uuid . '::' .  $this->credential4->name
        );
        expect($status->type)->toBeIn([CredentialTypeEnum::TYPE_POLLER_MACRO]);
        expect($status->credentialName)->toBeIn(['$POLLERMACRO$']);
    }
});

it('tests getIterator method with broker input/output configuration', function (): void {
    $credentials = new \ArrayIterator([$this->credential5]);
    $uuid = (new UUIDGenerator())->generateV4();
    $this->writeVaultRepository->method('upsert')->willReturn(
        [$this->credential5->name => 'secret::hashicorp_vault::vault/path/' . $uuid . '::' .  $this->credential5->name]
    );

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        readBrokerInputOutputRepository: $this->readBrokerInputOutputRepository,
        writeBrokerInputOutputRepository: $this->writeBrokerInputOutputRepository,
        writeAccRepository: $this->writeAccRepository,
        accCredentialMigrators: $this->accCredentialMigrators,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration,
        brokerInputOutputs: [5 => [$this->brokerInputOutputs]],
        accs: [$this->acc],
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($uuid);
        expect($status->resourceId)->toBeIn([5]);
        expect($status->vaultPath)->toBe('secret::hashicorp_vault::vault/path/'
            . $uuid . '::' .  $this->credential5->name
        );
        expect($status->type)->toBeIn([CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT]);
        expect($status->credentialName)->toBeIn(['my-output_db_password']);
    }
});

it('tests getIterator method with exception', function (): void {
    $credentials = new \ArrayIterator([$this->credential1]);

    $this->writeVaultRepository->method('upsert')->willThrowException(new \Exception('Test exception'));

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,

        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        writeOpenIdConfigurationRepository: $this->writeOpenIdConfigurationRepository,
        readBrokerInputOutputRepository: $this->readBrokerInputOutputRepository,
        writeBrokerInputOutputRepository: $this->writeBrokerInputOutputRepository,
        writeAccRepository: $this->writeAccRepository,
        accCredentialMigrators: $this->accCredentialMigrators,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration,
        brokerInputOutputs: [5 => [$this->brokerInputOutputs]],
        accs: [$this->acc],
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY);
        expect($status->message)->toBe('Test exception');
    }
});
