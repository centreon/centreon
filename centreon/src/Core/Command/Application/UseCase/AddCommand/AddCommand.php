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

namespace Core\Command\Application\UseCase\AddCommand;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Domain\Model\NewCommand;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\NewCommandMacro;
use Core\Common\Domain\TrimmedString;

final class AddCommand
{
    use LoggerTrait;

    /** @var CommandType[] */
    private $allowedCommandTypes = [];

    public function __construct(
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly WriteCommandRepositoryInterface $writeCommandRepository,
        private readonly AddCommandValidation $validation,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param AddCommandRequest $request
     * @param AddCommandPresenterInterface $presenter
     */
    public function __invoke(AddCommandRequest $request, AddCommandPresenterInterface $presenter): void
    {
        try {
            $this->allowedCommandTypes = $this->getAllowedCommandTypes();
            if ($this->allowedCommandTypes === [] || ! in_array($request->type, $this->allowedCommandTypes, true)) {
                $this->error(
                    "User doesn't have sufficient rights to add a command",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(CommandException::addNotAllowed())
                );

                return;
            }

            $this->validation->assertIsValidName($request);
            $this->validation->assertAreValidArguments($request);
            $this->validation->assertAreValidMacros($request);
            $this->validation->assertIsValidConnector($request);
            $this->validation->assertIsValidGraphTemplate($request);

            $newCommand = $this->createNewCommand($request);

            $newCommandId = $this->writeCommandRepository->add($newCommand);

            $this->info('New command created', ['command_id' => $newCommandId]);

            $command = $this->readCommandRepository->findById($newCommandId);
            if (! $command) {
                $presenter->presentResponse(
                    new ErrorResponse(CommandException::errorWhileRetrieving())
                );

                return;
            }

            $presenter->presentResponse($this->createResponse($command));
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (CommandException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    CommandException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(CommandException::errorWhileAdding($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param AddCommandRequest $request
     *
     * @throws \Exception
     * @throws AssertionFailedException
     *
     * @return NewCommand
     */
    private function createNewCommand(AddCommandRequest $request): NewCommand
    {
        $arguments = [];
        foreach ($request->arguments as $argument) {
            $arguments[] = new Argument(
                new TrimmedString($argument->name),
                new TrimmedString($argument->description ?? '')
            );
        }
        $macros = [];
        foreach ($request->macros as $macro) {
            $macros[] = new NewCommandMacro(
                $macro->type,
                $macro->name,
                $macro->description
            );
        }

        return new NewCommand(
            name: new TrimmedString($request->name),
            commandLine: new TrimmedString($request->commandLine),
            isShellEnabled: $request->isShellEnabled,
            type: $request->type,
            argumentExample: new TrimmedString($request->argumentExample),
            arguments: $arguments,
            macros: $macros,
            connectorId: $request->connectorId,
            graphTemplateId: $request->graphTemplateId,
        );
    }

    /**
     * @param Command $command
     *
     * @throws \Throwable
     *
     * @return AddCommandResponse
     */
    private function createResponse(Command $command): AddCommandResponse
    {
        $response = new AddCommandResponse();
        $response->id = $command->getId();
        $response->name = $command->getName();
        $response->type = $command->getType();
        $response->commandLine = $command->getCommandLine();
        $response->argumentExample = $command->getArgumentExample();
        $response->isShellEnabled = $command->isShellEnabled();
        $response->isActivated = $command->isActivated();
        $response->isLocked = $command->isLocked();
        $response->arguments = array_map(
            fn(Argument $argument) => [
                'name' => $argument->getName(),
                'description' => $argument->getDescription(),
            ],
            $command->getArguments(),
        );
        $response->macros = array_map(
            fn(CommandMacro $macro) => [
                'name' => $macro->getName(),
                'description' => $macro->getDescription(),
                'type' => $macro->getType(),
            ],
            $command->getMacros(),
        );
        $response->connector = null !== $command->getConnector()
            ? [
                'id' => $command->getConnector()->getId(),
                'name' => (string) $command->getConnector()->getName(),
            ]
            : null;
        $response->graphTemplate = null !== $command->getGraphTemplate()
            ? [
                'id' => $command->getGraphTemplate()->getId(),
                'name' => (string) $command->getGraphTemplate()->getName(),
            ]
            : null;

        return $response;
    }

    /**
     * @return CommandType[]
     */
    private function getAllowedCommandTypes(): array
    {
        $allowedCommandTypes = [];
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW)) {
            $allowedCommandTypes[] = CommandType::Check;
        }
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW)) {
            $allowedCommandTypes[] = CommandType::Notification;
        }
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW)) {
            $allowedCommandTypes[] = CommandType::Miscellaneous;
        }
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW)) {
            $allowedCommandTypes[] = CommandType::Discovery;
        }

        return $allowedCommandTypes;
    }
}
