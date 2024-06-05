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

namespace Tests\Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse
};
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Application\Repository\WriteVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\NewVaultConfigurationFactory;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\UpdateVaultConfiguration;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\UpdateVaultConfigurationRequest;
use Core\Security\Vault\Domain\Model\{Vault, VaultConfiguration};
use Security\Encryption;

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultConfigurationRepository = $this->createMock(WriteVaultConfigurationRepositoryInterface::class);
    $this->newVaultConfigurationFactory = $this->createMock(NewVaultConfigurationFactory::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present ForbiddenResponse when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->newVaultConfigurationFactory,
        $this->readVaultRepository,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())
        ->toBe(VaultConfigurationException::onlyForAdmin()->getMessage());
});

it('should present InvalidArgumentResponse when one parameter is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->any())
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $salt = $encryption->generateRandomString(VaultConfiguration::SALT_LENGTH);
    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        'myVaultConfiguration',
        '127.0.0.2',
        8200,
        'myStorageFolder',
        'myEncryptedRoleId',
        'myEncryptedSecretId',
        $salt
    );
    $this->readVaultConfigurationRepository
        ->expects($this->any())
        ->method('find')
        ->willReturn($vaultConfiguration);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->newVaultConfigurationFactory,
        $this->readVaultRepository,
        $this->user
    );

    $invalidAddress = '._@';
    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->address = $invalidAddress;
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->rootPath = 'myStorageFolder';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        AssertionException::ipOrDomain(
            $invalidAddress,
            'VaultConfiguration::address'
        )->getMessage()
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

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->newVaultConfigurationFactory,
        $this->readVaultRepository,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToUpdate()->getMessage()
    );
});

it('should present NoContentResponse when vault configuration is created with success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $vaultConfiguration = new VaultConfiguration(
        $encryption,
        'myVaultConfiguration',
        '127.0.0.2',
        8201,
        'myStorageFolder',
        'myEncryptedRoleId',
        'myEncryptedSecretId',
        'mySalt'
    );
    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn($vaultConfiguration);

    $this->readVaultRepository
        ->expects($this->any())
        ->method('testVaultConnection')
        ->willReturn(true);

    $presenter = new UpdateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new UpdateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->newVaultConfigurationFactory,
        $this->readVaultRepository,
        $this->user
    );

    $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
    $updateVaultConfigurationRequest->address = '127.0.0.1';
    $updateVaultConfigurationRequest->port = 8200;
    $updateVaultConfigurationRequest->rootPath = 'myStorageFolder';
    $updateVaultConfigurationRequest->roleId = 'myRole';
    $updateVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $updateVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
