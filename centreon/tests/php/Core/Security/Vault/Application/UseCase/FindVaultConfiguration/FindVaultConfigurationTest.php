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

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present Forbidden Response when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->user
    );

    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    expect($presenter->data?->getMessage())
        ->toBe(VaultConfigurationException::onlyForAdmin()->getMessage());
});

it('should present NotFound Response when vault configuration does not exist for a given id', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->user
    );

    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->data?->getMessage())->toBe(
        (new NotFoundResponse('Vault configuration'))->getMessage()
    );
});

it('should present ErrorResponse when an unhandled error occurs', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->user
    );

    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->data?->getMessage())->toBe(
        VaultConfigurationException::impossibleToFind()->getMessage()
    );
});

it('should present FindVaultConfigurationResponse', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        'myVaultConfiguration',
        '127.0.0.1',
        8200,
        'myStorageFolder',
        'mySalt',
        'myEncryptedRoleId',
        'myEncryptedSecretId'
    );


    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn($vaultConfiguration);

    $presenter = new FindVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new FindVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->user
    );

    $findVaultConfigurationResponse = new FindVaultConfigurationResponse();
    $findVaultConfigurationResponse->address = $vaultConfiguration->getAddress();
    $findVaultConfigurationResponse->port = $vaultConfiguration->getPort();
    $findVaultConfigurationResponse->rootPath = $vaultConfiguration->getRootPath();

    $useCase($presenter);

    expect($presenter->data)->toBeInstanceOf(FindVaultConfigurationResponse::class);
    expect($presenter->data->address)->toBe($findVaultConfigurationResponse->address);
    expect($presenter->data->port)->toBe($findVaultConfigurationResponse->port);
    expect($presenter->data->rootPath)->toBe($findVaultConfigurationResponse->rootPath);
});
