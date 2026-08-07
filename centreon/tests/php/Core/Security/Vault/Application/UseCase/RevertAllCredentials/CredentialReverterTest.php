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

namespace Tests\Core\Security\Vault\Application\UseCase\RevertAllCredentials;

use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type as AccType;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\Type as BrokerIOType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
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
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\RevertAllCredentials\CredentialReverter;
use Core\Security\Vault\Application\UseCase\RevertAllCredentials\Reverter\VmWareV6CredentialReverter;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use DateTimeImmutable;
use Utility\UUIDGenerator;

/**
 * Build a stored vault path for the given uuid and vault key.
 */
function vaultPath(string $uuid, string $key): string
{
    return 'secret::hashicorp_vault::vault/path/' . $uuid . '::' . $key;
}

beforeEach(function (): void {
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
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
    $this->accCredentialReverters = [$this->createMock(VmWareV6CredentialReverter::class)];

    $this->uuid = (new UUIDGenerator())->generateV4();

    $this->credential1 = new CredentialDto();
    $this->credential1->resourceId = 1;
    $this->credential1->type = CredentialTypeEnum::TYPE_HOST;
    $this->credential1->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
    $this->credential1->value = vaultPath($this->uuid, VaultConfiguration::HOST_SNMP_COMMUNITY_KEY);

    $this->credential2 = new CredentialDto();
    $this->credential2->resourceId = 2;
    $this->credential2->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    $this->credential2->name = VaultConfiguration::HOST_SNMP_COMMUNITY_KEY;
    $this->credential2->value = vaultPath($this->uuid, VaultConfiguration::HOST_SNMP_COMMUNITY_KEY);

    $this->credential3 = new CredentialDto();
    $this->credential3->resourceId = 1;
    $this->credential3->type = CredentialTypeEnum::TYPE_SERVICE;
    $this->credential3->name = '_MACRO_SERVICE1';
    $this->credential3->value = vaultPath($this->uuid, '_MACRO_SERVICE1');

    $this->credential4 = new CredentialDto();
    $this->credential4->resourceId = 4;
    $this->credential4->type = CredentialTypeEnum::TYPE_POLLER_MACRO;
    $this->credential4->name = '$POLLERMACRO$';
    $this->credential4->value = vaultPath($this->uuid, '$POLLERMACRO$');

    $this->credential5 = new CredentialDto();
    $this->credential5->resourceId = 5;
    $this->credential5->type = CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT;
    $this->credential5->name = 'my-output_db_password';
    $this->credential5->value = vaultPath($this->uuid, 'my-output_db_password');

    $this->hosts = [
        new Host(1, 1, 'Host1', '127.0.0.1'),
    ];

    $this->hostTemplates = [
        new HostTemplate(2, 'HostTemplate1', 'HostTemplate1'),
    ];

    $this->hostMacro = new Macro(null, 1, '_MACRO_HOST1', 'value');
    $this->hostMacro->setIsPassword(true);
    $this->hostMacros = [$this->hostMacro];

    $this->serviceMacro = new Macro(null, 1, '_MACRO_SERVICE1', 'value');
    $this->serviceMacro->setIsPassword(true);
    $this->serviceMacros = [$this->serviceMacro];

    $this->pollerMacro = new PollerMacro(4, '$POLLERMACRO$', 'value', null, true, true);
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
    $this->openIdProviderConfiguration = new Configuration(
        1,
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
            'db_password' => vaultPath($this->uuid, 'my-output_db_password'),
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

    $this->buildReverter = fn (\ArrayIterator $credentials): CredentialReverter => new CredentialReverter(
        credentials: $credentials,
        readVaultRepository: $this->readVaultRepository,
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
        accCredentialReverters: $this->accCredentialReverters,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
        openIdProviderConfiguration: $this->openIdProviderConfiguration,
        brokerInputOutputs: [5 => [$this->brokerInputOutputs]],
        accs: [$this->acc],
    );

    // Return, for any vault path, a bag keyed by the credential key it ends with.
    $this->readVaultRepository->method('findFromPath')->willReturnCallback(function (string $path): array {
        $segments = explode('::', $path);
        $key = end($segments);

        return [$key => 'plaintext-' . $key];
    });
});

it('reverts hosts, host templates and service macros back to the database', function (): void {
    $credentials = new \ArrayIterator([$this->credential1, $this->credential2, $this->credential3]);

    $this->writeHostRepository->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (Host $host): bool => $host->getSnmpCommunity() === 'plaintext-' . VaultConfiguration::HOST_SNMP_COMMUNITY_KEY
        ));
    $this->writeHostTemplateRepository->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (HostTemplate $hostTemplate): bool => $hostTemplate->getSnmpCommunity() === 'plaintext-' . VaultConfiguration::HOST_SNMP_COMMUNITY_KEY
        ));
    $this->writeServiceMacroRepository->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (Macro $macro): bool => $macro->getValue() === 'plaintext-_MACRO_SERVICE1'
        ));

    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($this->uuid);
        expect($status->resourceId)->toBeIn([1, 2]);
        expect($status->vaultPath)->toStartWith(VaultConfiguration::VAULT_PATH_PATTERN);
        expect($status->type)->toBeIn([
            CredentialTypeEnum::TYPE_HOST,
            CredentialTypeEnum::TYPE_HOST_TEMPLATE,
            CredentialTypeEnum::TYPE_SERVICE,
        ]);
        expect($status->credentialName)->toBeIn([VaultConfiguration::HOST_SNMP_COMMUNITY_KEY, '_MACRO_SERVICE1']);
    }
});

it('reverts poller macros back to the database', function (): void {
    $credentials = new \ArrayIterator([$this->credential4]);

    $this->writePollerMacroRepository->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (PollerMacro $macro): bool => $macro->getValue() === 'plaintext-$POLLERMACRO$'
        ));

    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($this->uuid);
        expect($status->resourceId)->toBe(4);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_POLLER_MACRO);
        expect($status->credentialName)->toBe('$POLLERMACRO$');
    }
});

it('reverts broker input/output passwords back to the database', function (): void {
    $credentials = new \ArrayIterator([$this->credential5]);

    $this->writeBrokerInputOutputRepository->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (BrokerInputOutput $io): bool => $io->getParameters()['db_password'] === 'plaintext-my-output_db_password'
        ));

    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe($this->uuid);
        expect($status->resourceId)->toBe(5);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_BROKER_INPUT_OUTPUT);
        expect($status->credentialName)->toBe('my-output_db_password');
    }
});

it('yields an error when the vault does not contain the expected key', function (): void {
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->readVaultRepository->method('findFromPath')->willReturn([]);

    $credentials = new \ArrayIterator([$this->credential1]);
    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY);
    }
});

it('yields an error when reading from the vault fails', function (): void {
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->readVaultRepository->method('findFromPath')->willThrowException(new \Exception('Test exception'));

    $credentials = new \ArrayIterator([$this->credential1]);
    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe(VaultConfiguration::HOST_SNMP_COMMUNITY_KEY);
        expect($status->message)->toBe('Test exception');
    }
});

it('deletes the vault secret once per unique uuid after restoring, deduplicating shared uuids', function (): void {
    // credential1 (host) and credential2 (host template) share the same uuid and vault path.
    $credentials = new \ArrayIterator([$this->credential1, $this->credential2]);

    $this->writeVaultRepository->expects($this->once())
        ->method('setCustomPath')
        ->with(AbstractVaultRepository::HOST_VAULT_PATH);
    $this->writeVaultRepository->expects($this->once())
        ->method('delete')
        ->with($this->uuid);

    $reverter = ($this->buildReverter)($credentials);

    foreach ($reverter as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
    }
});

it('keeps a shared vault secret when a credential using it failed to revert', function (): void {
    // credential1 (host) and credential2 (host template) share the same uuid and vault path.
    // credential2 fails to revert, so the shared secret must be kept: deleting it would leave
    // the still-referenced database path unrecoverable.
    $this->credential2->value = vaultPath($this->uuid, 'missing-key');

    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->readVaultRepository->method('findFromPath')->willReturnCallback(function (string $path): array {
        $segments = explode('::', $path);
        $key = end($segments);
        if ($key === 'missing-key') {
            return []; // simulate a credential that cannot be read back
        }

        return [$key => 'plaintext-' . $key];
    });

    $credentials = new \ArrayIterator([$this->credential1, $this->credential2]);

    $this->writeVaultRepository->expects($this->never())->method('delete');

    $reverter = ($this->buildReverter)($credentials);
    $statuses = iterator_to_array($reverter);

    expect($statuses)->toHaveCount(2);
    expect($statuses[0])->toBeInstanceOf(CredentialRecordedDto::class);
    expect($statuses[0]->resourceId)->toBe(1);
    expect($statuses[1])->toBeInstanceOf(CredentialErrorDto::class);
    expect($statuses[1]->resourceId)->toBe(2);
});

it('tolerates a vault deletion failure and still reports the credential as reverted', function (): void {
    $credentials = new \ArrayIterator([$this->credential1]);

    $this->writeVaultRepository->method('delete')
        ->willThrowException(new \Exception('vault delete failed'));

    $reverter = ($this->buildReverter)($credentials);

    $statuses = iterator_to_array($reverter);

    expect($statuses)->toHaveCount(1);
    expect($statuses[0])->toBeInstanceOf(CredentialRecordedDto::class);
    expect($statuses[0]->resourceId)->toBe(1);
});
