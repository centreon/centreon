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

namespace Tests\Core\Security\Vault\Application\UseCase\FindVaultConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{ErrorResponse, NotFoundResponse, ForbiddenResponse};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface
};
use Core\Security\Vault\Application\UseCase\FindVaultConfiguration\{
    FindVaultConfiguration,
    FindVaultConfigurationRequest,
    FindVaultConfigurationResponse
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

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationRequest = new FindVaultConfigurationRequest();

    $useCase($presenter, $findVaultConfigurationRequest);

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

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationRequest = new FindVaultConfigurationRequest();

    $useCase($presenter, $findVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault provider'))->getMessage()
    );
});

it('should present NotFound Response when vault configuration does not exist for a given id', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationRequest = new FindVaultConfigurationRequest();

    $useCase($presenter, $findVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault configuration'))->getMessage()
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

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationRequest = new FindVaultConfigurationRequest();

    $useCase($presenter, $findVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToFind()->getMessage()
    );
});

it('should present FindVaultConfigurationResponse', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $vault = new Vault(1, 'myVaultProvider');

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

    $findVaultConfigurationRequest = new FindVaultConfigurationRequest();
    $findVaultConfigurationRequest->vaultConfigurationId = $vaultConfiguration->getId();
    $findVaultConfigurationRequest->vaultId = $vaultConfiguration->getVault()->getId();

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($findVaultConfigurationRequest->vaultConfigurationId)
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('findById')
        ->with($findVaultConfigurationRequest->vaultConfigurationId)
        ->willReturn($vaultConfiguration);

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $findVaultConfigurationResponse = new FindVaultConfigurationResponse();
    $findVaultConfigurationResponse->vaultConfiguration = [
        'id' => $vaultConfiguration->getId(),
        'name' => $vaultConfiguration->getName(),
        'vault_id' => $vaultConfiguration->getVault()->getId(),
        'url' => $vaultConfiguration->getAddress(),
        'port' => $vaultConfiguration->getPort(),
        'root_path' => $vaultConfiguration->getRootPath()
    ];

    $useCase($presenter, $findVaultConfigurationRequest);

    expect($presenter->response)->toBeInstanceOf(FindVaultConfigurationResponse::class);
    expect($presenter->response->vaultConfiguration)->toBe($findVaultConfigurationResponse->vaultConfiguration);
});
