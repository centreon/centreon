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
use Core\Option\Application\Repository\WriteOptionRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialErrorDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialRecordedDto;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialTypeEnum;


beforeEach(function (): void {

    $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class);
    $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class);
    $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class);
    $this->writePollerMacroRepository = $this->createMock(WritePollerMacroRepositoryInterface::class);
    $this->writeOptionRepository = $this->createMock(WriteOptionRepositoryInterface::class);

    $this->credential1 = new CredentialDto();
    $this->credential1->resourceId = 1;
    $this->credential1->type = CredentialTypeEnum::TYPE_HOST;
    $this->credential1->name = '_HOSTSNMPCOMMUNITY';
    $this->credential1->value = 'community';

    $this->credential2 = new CredentialDto();
    $this->credential2->resourceId = 2;
    $this->credential2->type = CredentialTypeEnum::TYPE_HOST_TEMPLATE;
    $this->credential2->name = '_HOSTSNMPCOMMUNITY';
    $this->credential2->value = 'community';

    $this->credential3 = new CredentialDto();
    $this->credential3->resourceId = 3;
    $this->credential3->type = CredentialTypeEnum::TYPE_SERVICE;
    $this->credential3->name = '_SERVICEMACRO';
    $this->credential3->value = 'macro';

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
});

it('tests getIterator method with hosts, hostTemplates and service macros', function (): void {
    $credentials = new \ArrayIterator([$this->credential1, $this->credential2, $this->credential3]);

    $this->writeVaultRepository->method('upsert')->willReturn('vault/path');

    $credentialMigrator = new CredentialMigrator(
        credentials: $credentials,
        writeVaultRepository: $this->writeVaultRepository,
        writeHostRepository: $this->writeHostRepository,
        writeHostTemplateRepository: $this->writeHostTemplateRepository,
        writeHostMacroRepository: $this->writeHostMacroRepository,
        writeServiceMacroRepository: $this->writeServiceMacroRepository,
        writeOptionRepository: $this->writeOptionRepository,
        writePollerMacroRepository: $this->writePollerMacroRepository,
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
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
        hosts: $this->hosts,
        hostTemplates: $this->hostTemplates,
        hostMacros: $this->hostMacros,
        serviceMacros: $this->serviceMacros,
        pollerMacros: $this->pollerMacros,
    );

    foreach ($credentialMigrator as $status) {
        expect($status)->toBeInstanceOf(CredentialErrorDto::class);
        expect($status->resourceId)->toBe(1);
        expect($status->type)->toBe(CredentialTypeEnum::TYPE_HOST);
        expect($status->credentialName)->toBe('_HOSTSNMPCOMMUNITY');
        expect($status->message)->toBe('Test exception');
    }
});
