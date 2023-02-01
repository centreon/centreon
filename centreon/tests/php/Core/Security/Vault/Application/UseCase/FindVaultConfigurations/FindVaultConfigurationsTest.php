<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Security\Vault\Application\UseCase\FindVaultConfigurations;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{ErrorResponse, NotFoundResponse, ForbiddenResponse};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultRepositoryInterface,
    ReadVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Application\UseCase\FindVaultConfigurations\{
    FindVaultConfigurations,
    FindVaultConfigurationsResponse
};
use Core\Security\Vault\Domain\Model\{Vault, VaultConfiguration};
use Security\Encryption;

beforeEach(function () {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present Forbidden Response when user is not admin', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $presenter = new FindVaultConfigurationsPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfigurations(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $vaultId = 1;

    $useCase($presenter, $vaultId);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe('Only admin user can create vault configuration');
});

it('should present NotFound Response when vault provider does not exist', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $presenter = new FindVaultConfigurationsPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfigurations(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $vaultId = 2;

    $useCase($presenter, $vaultId);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault provider'))->getMessage()
    );
});

it('should present ErrorResponse when an unhandled error occurs', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    $presenter = new FindVaultConfigurationsPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfigurations(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $vaultId = 3;

    $useCase($presenter, $vaultId);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToFind()->getMessage()
    );
});

it('should present FindVaultConfigurationsResponse', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $vault = new Vault(1, 'myVaultProvider');

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        1,
        'myVaultConfiguration',
        $vault,
        '127.0.0.1',
        8200,
        'myStorageFolder',
        'mySalt',
        'myEncryptedRoleId',
        'myEncryptedSecretId'
    );

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findVaultConfigurationsByVault')
        ->willReturn([$vaultConfiguration]);

    $presenter = new FindVaultConfigurationsPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfigurations(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationsResponse = new FindVaultConfigurationsResponse();
    $findVaultConfigurationsResponse->vaultConfigurations = [
        [
            'id' => $vaultConfiguration->getId(),
            'name' => $vaultConfiguration->getName(),
            'vault_id' => $vault->getId(),
            'url' => $vaultConfiguration->getAddress(),
            'port' => $vaultConfiguration->getPort(),
            'storage' => $vaultConfiguration->getRootPath()
        ]
    ];

    $useCase($presenter, $vault->getId());

    expect($presenter->response)
        ->toBeInstanceOf(FindVaultConfigurationsResponse::class)
        ->and($presenter->response->vaultConfigurations)
        ->toBe($findVaultConfigurationsResponse->vaultConfigurations);
});
