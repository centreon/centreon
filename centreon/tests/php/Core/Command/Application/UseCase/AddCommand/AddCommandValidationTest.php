<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Command\Application\UseCase\AddCommand;

use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\UseCase\AddCommand\AddCommandRequest;
use Core\Command\Application\UseCase\AddCommand\AddCommandValidation;
use Core\Command\Application\UseCase\AddCommand\ArgumentDto;
use Core\Command\Application\UseCase\AddCommand\MacroDto;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Connector\Application\Repository\ReadConnectorRepositoryInterface;
use Core\GraphTemplate\Application\Repository\ReadGraphTemplateRepositoryInterface;

beforeEach(function (): void {
    $this->validation = new AddCommandValidation(
        readCommandRepository: $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        readConnectorRepository: $this->readConnectorRepository = $this->createMock(ReadConnectorRepositoryInterface::class),
        readGraphTemplateRepository: $this->readGraphTemplateRepository = $this->createMock(ReadGraphTemplateRepositoryInterface::class),
    );
});

it('throws an exception when name is already used', function (): void {
    $request = new AddCommandRequest();
    $request->name = 'name test';

    $this->readCommandRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validation->assertIsValidName($request);
})->throws(
    CommandException::class,
    CommandException::nameAlreadyExists('name test')->getMessage()
);

it('throws an exception when an argument is invalid', function (): void {
    $request = new AddCommandRequest();
    $request->arguments[] = new ArgumentDto('arg-name', 'arg-desc');

    $this->validation->assertAreValidArguments($request);
})->throws(
    CommandException::class,
    CommandException::invalidArguments(['arg-name'])->getMessage()
);

it('throws an exception when a macro is invalid', function (): void {
    $request = new AddCommandRequest();
    $request->macros[] = new MacroDto('macro-name', CommandMacroType::Host,  'macro-desc');

    $this->validation->assertAreValidMacros($request);
})->throws(
    CommandException::class,
    CommandException::invalidMacros(['macro-name'])->getMessage()
);

it('throws an exception when connector ID does not exist', function (): void {
    $request = new AddCommandRequest();
    $request->connectorId = 12;

    $this->readConnectorRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidConnector($request);
})->throws(
    CommandException::class,
    CommandException::idDoesNotExist('connectorId', 12)->getMessage()
);

it('throws an exception when graph template ID does not exist', function (): void {
    $request = new AddCommandRequest();
    $request->graphTemplateId = 12;

    $this->readGraphTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidGraphTemplate($request);
})->throws(
    CommandException::class,
    CommandException::idDoesNotExist('graphTemplateId', 12)->getMessage()
);
