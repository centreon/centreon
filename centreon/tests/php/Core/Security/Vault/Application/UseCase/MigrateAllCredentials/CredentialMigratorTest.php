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

it('tests getIterator method with hosts, hostTemplates and service macros', function (): void {
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

it('tests getIterator method with exception', function (): void {
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