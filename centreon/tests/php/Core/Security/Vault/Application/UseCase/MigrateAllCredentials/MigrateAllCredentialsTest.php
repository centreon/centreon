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

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\PollerMacro\Application\Repository\ReadPollerMacroRepositoryInterface;
use Core\PollerMacro\Application\Repository\WritePollerMacroRepositoryInterface;
use Core\Security\Vault\Application\Exceptions\VaultException;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\CredentialMigrator;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentials;
use Core\Security\Vault\Application\UseCase\MigrateAllCredentials\MigrateAllCredentialsResponse;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Security\Interfaces\EncryptionInterface;

beforeEach(function (): void {
    $this->encryption = $this->createMock(EncryptionInterface::class);
    $this->useCase = new MigrateAllCredentials(
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
        $this->readPollerMacroRepository = $this->createMock(ReadPollerMacroRepositoryInterface::class),
        $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class),
        $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class),
        $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class),
        $this->writePollerMacroRepository = $this->createMock(WritePollerMacroRepositoryInterface::class),
    );
});

it('should present an Error Response when no vault are configured', function (): void {
    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(null);

    $presenter = new MigrateAllCredentialsPresenterStub();
    ($this->useCase)($presenter);

    expect($presenter->response)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->response->getMessage())->toBe(VaultException::noVaultConfigured()->getMessage());
});

it('should present a MigrateAllCredentialsResponse when no error occurs', function (): void {
    $vaultConfiguration = new VaultConfiguration(
        encryption: $this->encryption,
        name: 'vault',
        address: '127.0.0.1',
        port: 443,
        rootPath: 'mystorage',
        encryptedRoleId: 'role-id',
        encryptedSecretId: 'secret-id',
        salt: 'labaleine'
    );

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn($vaultConfiguration);

    $presenter = new MigrateAllCredentialsPresenterStub();
    ($this->useCase)($presenter);
    expect($presenter->response)->toBeInstanceOf(MigrateAllCredentialsResponse::class)
        ->and($presenter->response->results)->toBeInstanceOf(CredentialMigrator::class);
});
