<?php

namespace Tests\Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsPresenterInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsResponse;

it('tests getIterator method with hosts, hostTemplates and service macros', function () {
    $credential1 = new CredentialDto();
    $credential1->resourceId = 1;
    $credential1->type = CredentialTypeEnum::TYPE_HOST;
    $credential1->name = '_HOSTSNMPCOMMUNITY';
    $credential1->value = 'community';

    $credential2 = new CredentialDto();
    $credential2->resourceId = 2;
    $credential2->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    $credential2->name = '_HOSTSNMPCOMMUNITY';
    $credential2->value = 'community';

    $credential3 = new CredentialDto();
    $credential3->resourceId = 3;
    $credential3->type = CredentialTypeEnum::TYPE_SERVICE;
    $credential3->name = '_SERVICEMACRO';
    $credential3->value = 'macro';

    $credentials = new \ArrayIterator([$credential1, $credential2, $credential3]);

    $writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);

    $writeVaultRepository->method('upsert')->willReturn('vault/path');

    $hosts = [
        new Host(1, 1, 'Host1', '127.0.0.1'),
    ];

    $hostTemplates = [
        new HostTemplate(2, 'HostTemplate1', 'HostTemplate1'),
    ];

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $writeVaultRepository,
        writeHostRepository: $writeHostRepository,
        writeHostTemplateRepository: $writeHostTemplateRepository,
        writeHostMacroRepository: $writeHostMacroRepository,
        writeServiceMacroRepository: $writeServiceMacroRepository,
        hosts: $hosts,
        hostTemplates: $hostTemplates
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialRecordedDto::class);
        expect($status->uuid)->toBe('path');
        expect($status->resourceId)->toBeIn([1, 2, 3]);
        expect($status->vaultPath)->toBe('vault/path');
        expect($status->type)->toBeIn([CredentialTypeEnum::TYPE_HOST, CredentialTypeEnum::TYPE_HOST_TEMPLATE, CredentialTypeEnum::TYPE_SERVICE]);
        expect($status->credentialName)->toBeIn(['_HOSTSNMPCOMMUNITY', '_SERVICEMACRO']);
    }
});

it('tests getIterator method with exception', function () {
    $credential = new CredentialDto();
    $credential->resourceId = 1;
    $credential->type = CredentialTypeEnum::TYPE_HOST;
    $credential->name = '_HOSTSNMPCOMMUNITY';
    $credential->value = 'community';

    $credentials = new \ArrayIterator([$credential]);

    $writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);

    $writeVaultRepository->method('upsert')->willThrowException(new \Exception('Test exception'));

    $hosts = [
        new Host(1, 1, 'Host1', '127.0.0.1'),
    ];

    $hostTemplates = [
        new HostTemplate(2, 'HostTemplate1', 'HostTemplate1'),
    ];

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $writeVaultRepository,
        writeHostRepository: $writeHostRepository,
        writeHostTemplateRepository: $writeHostTemplateRepository,
        writeHostMacroRepository: $writeHostMacroRepository,
        writeServiceMacroRepository: $writeServiceMacroRepository,
        hosts: $hosts,
        hostTemplates: $hostTemplates
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe('_HOSTSNMPCOMMUNITY');
        expect($status->message)->toBe('Test exception');
    }
});