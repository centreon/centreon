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

namespace Tests\Core\Security\Vault\Application\UseCase\DeleteVaultConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    NoContentResponse,
    NotFoundResponse
};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Application\UseCase\DeleteVaultConfiguration\{
    DeleteVaultConfiguration,
    DeleteVaultConfigurationRequest
};
use Core\Security\Vault\Domain\Model\{Vault, VaultConfiguration};
use Security\Encryption;

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultConfigurationRepository = $this->createMock(WriteVaultConfigurationRepositoryInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present ForbiddenResponse when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $presenter = new DeleteVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new DeleteVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();

    $useCase($presenter, $deleteVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe('Only admin user can create vault configuration');
});

it('should present NotFoundResponse when vault provider does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $presenter = new DeleteVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new DeleteVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();
    $deleteVaultConfigurationRequest->vaultConfigurationId = 1;
    $deleteVaultConfigurationRequest->typeId = 3;

    $useCase($presenter, $deleteVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault provider'))->getMessage()
    );
});

it('should present NotFoundResponse when vault configuration does not exist for a given id', function (): void {
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

    $presenter = new DeleteVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new deleteVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();
    $deleteVaultConfigurationRequest->vaultConfigurationId = 2;
    $deleteVaultConfigurationRequest->typeId = 1;

    $useCase($presenter, $deleteVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault configuration'))->getMessage()
    );
});

it('should present ErrorResponse when an unhandled error occurs', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    $presenter = new DeleteVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new DeleteVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();

    $useCase($presenter, $deleteVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToDelete()->getMessage()
    );
});

it('should present NoContentResponse when vault configuration is deleted with success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $presenter = new DeleteVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new DeleteVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->user
    );

    $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();
    $deleteVaultConfigurationRequest->vaultConfigurationId = 2;
    $deleteVaultConfigurationRequest->typeId = 1;

    $useCase($presenter, $deleteVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
