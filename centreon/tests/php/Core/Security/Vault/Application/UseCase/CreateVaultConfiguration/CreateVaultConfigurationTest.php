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

namespace Tests\Core\Security\Vault\Application\UseCase\CreateVaultConfiguration;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\{
    CreatedResponse,
    ErrorResponse,
    ForbiddenResponse,
    InvalidArgumentResponse,
    NotFoundResponse
};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultConfigurationRepositoryInterface,
    ReadVaultRepositoryInterface,
    WriteVaultConfigurationRepositoryInterface
};
use Core\Security\Vault\Application\UseCase\CreateVaultConfiguration\{
    CreateVaultConfiguration,
    CreateVaultConfigurationRequest,
    NewVaultConfigurationFactory
};
use Core\Security\Vault\Domain\Model\{NewVaultConfiguration, Vault, VaultConfiguration};
use Security\Encryption;

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
    $this->writeVaultConfigurationRepository = $this->createMock(WriteVaultConfigurationRepositoryInterface::class);
    $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->factory = $this->createMock(NewVaultConfigurationFactory::class);
    $this->user = $this->createMock(ContactInterface::class);
});

it('should present ForbiddenResponse when user is not admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe('Only admin user can create vault configuration');
});

it('should present InvalidArgumentResponse when vault configuration already exists', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $encryption = new Encryption();
    $encryption->setFirstKey("myFirstKey");

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('existsSameConfiguration')
        ->willReturn(true);

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::configurationExists()->getMessage()
    );
});

it('should present InvalidArgumentResponse when one parameter is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('existsSameConfiguration')
        ->willReturn(false);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $invalidName = '';

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
    $createVaultConfigurationRequest->name = $invalidName;
    $createVaultConfigurationRequest->typeId = 1;
    $createVaultConfigurationRequest->address = '127.0.0.1';
    $createVaultConfigurationRequest->port = 8200;
    $createVaultConfigurationRequest->rootPath = 'myStorage';
    $createVaultConfigurationRequest->roleId = 'myRole';
    $createVaultConfigurationRequest->secretId = 'mySecretId';

    $this->factory
        ->expects($this->once())
        ->method('create')
        ->with($createVaultConfigurationRequest)
        ->willThrowException(
            new InvalidArgumentException(
                AssertionException::minLength(
                    $invalidName,
                    strlen($invalidName),
                    NewVaultConfiguration::MIN_LENGTH,
                    'NewVaultConfiguration::name'
                )->getMessage(),
                1
            )
        );

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        AssertionException::minLength(
            $invalidName,
            strlen($invalidName),
            NewVaultConfiguration::MIN_LENGTH,
            'NewVaultConfiguration::name'
        )->getMessage()
    );
});

it('should present NotFoundResponse when vault provider does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('existsSameConfiguration')
        ->willReturn(false);

    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
    $createVaultConfigurationRequest->name = 'myVault';
    $createVaultConfigurationRequest->typeId = 3;
    $createVaultConfigurationRequest->address = '127.0.0.1';
    $createVaultConfigurationRequest->port = 8200;
    $createVaultConfigurationRequest->rootPath = 'myStorage';
    $createVaultConfigurationRequest->roleId = 'myRole';
    $createVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NotFoundResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        (new NotFoundResponse('Vault provider'))->getMessage()
    );
});

it('should present ErrorResponse when an unhandled error occurs', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('existsSameConfiguration')
        ->willThrowException(new \Exception());

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($presenter->getResponseStatus()?->getMessage())->toBe(
        VaultConfigurationException::impossibleToCreate()->getMessage()
    );
});

it('should present CreatedResponse when vault configuration is created with success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readVaultConfigurationRepository
        ->expects($this->once())
        ->method('existsSameConfiguration')
        ->willReturn(false);

    $vault = new Vault(1, 'myVaultProvider');
    $this->readVaultRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $presenter = new CreateVaultConfigurationPresenterStub($this->presenterFormatter);
    $useCase = new CreateVaultConfiguration(
        $this->readVaultConfigurationRepository,
        $this->writeVaultConfigurationRepository,
        $this->readVaultRepository,
        $this->factory,
        $this->user
    );

    $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
    $createVaultConfigurationRequest->name = 'myVault';
    $createVaultConfigurationRequest->typeId = $vault->getId();
    $createVaultConfigurationRequest->address = '127.0.0.1';
    $createVaultConfigurationRequest->port = 8200;
    $createVaultConfigurationRequest->rootPath = 'myStorage';
    $createVaultConfigurationRequest->roleId = 'myRoleId';
    $createVaultConfigurationRequest->secretId = 'mySecretId';

    $useCase($presenter, $createVaultConfigurationRequest);

    expect($presenter->getResponseStatus())->toBeInstanceOf(CreatedResponse::class);
});
