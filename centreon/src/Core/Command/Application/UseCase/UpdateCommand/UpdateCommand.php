<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Application\UseCase\UpdateCommand;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse,
};
use Core\Common\Domain\SimpleEntity;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Command\Application\Repository\{
    ReadCommandRepositoryInterface,
    WriteCommandRepositoryInterface,
};
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\Argument;
use Core\Common\Domain\TrimmedString;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Command\Domain\Model\CommandType;
use Centreon\Domain\Contact\Contact;
use Core\Command\Application\Exception\CommandException;
use Core\Application\Common\UseCase\PresenterInterface;


final class UpdateCommand
{
    use LoggerTrait;

    /**
     * Summary of __construct
     * @param ReadCommandRepositoryInterface $readCommandRepository
     * @param WriteCommandRepositoryInterface $writeCommandRepository
     * @param UpdateCommandValidator $validator
     * @param ContactInterface $user
     */
    public function __construct(
         private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly WriteCommandRepositoryInterface $writeCommandRepository,
        private readonly UpdateCommandValidator $validator,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param UpdateCommandRequest $request
     * @param PresenterInterface $presenter
     */
    public function __invoke(UpdateCommandRequest $request, PresenterInterface $presenter): void
    {
        try {
            $command = $this->readCommandRepository->findById($request->id);
            if(! $command) {
                $this->info('Command not found', ['command_id' => $request->id]);
                $presenter->setResponseStatus(new NotFoundResponse('Command'));
                return;
            }

            $commandTypes = $this->retrieveCommandTypesBasedOnContactRights();
            if ($commandTypes === [] || ! in_array($command->getType(), $commandTypes, true)) {
                $this->error(
                    "User doesn't have sufficient rights to update commands",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(CommandException::updateNotAllowed())
                );

                return;
            }

            $this->validator->assertNameDoesNotAlreadyExists($command, $request->name);
            $this->validator->assertAreValidArguments($request);
            $this->validator->assertAreValidMacros($request);
            $this->validator->assertIsValidConnector($request);
            $this->validator->assertIsValidGraphTemplate($request);

            $this->updateCommand($request, $command);

            $this->info('Command updated', ['command_id' => $command->getId()]);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (AssertionFailedException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

        } catch (CommandException $ex) {

                $resp = match ($ex->getCode()) {
                    CommandException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                };

                 $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

                 $presenter->setResponseStatus($resp);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(CommandException::errorWhileUpdating($ex)));

        }
    }

    /**
     * Update the configuration options of the host group.
     *
     * @param UpdateCommandRequest $request
     * @param Command $originalCommand
     *
     * @throws InvalidGeoCoordException|\Throwable
     */
    private function updateCommand(UpdateCommandRequest $request, Command $originalCommand): void
    {
        $arguments = [];
        foreach ($request->arguments as $argument) {
            $arguments[] = new Argument(
                new TrimmedString($argument->name),
                new TrimmedString($argument->description ?? '')
            );
        }
        $macros = [];
        foreach ($request->macros as $key=>$macro) {


            $macros[] = new CommandMacro(
                $originalCommand->getId(),
                $macro->type,
                $macro->name,
            );
            $macros[$key]->setDescription($macro->description);
        }


        $updatedCommand = new Command(
            id: $request->id,
            name: (new TrimmedString($request->name))->value,
            commandLine: (new TrimmedString($request->commandLine))->value,
            isShellEnabled: $request->isShellEnabled,
            isActivated: $request->isActivated,
            argumentExample: (new TrimmedString($request->argumentExample))->value,
            arguments: $arguments,
            macros: $macros,
            connector: $request->connectorId !== null ? new SimpleEntity($request->connectorId, null, '') : null,
            graphTemplate: $request->graphTemplateId !== null ? new SimpleEntity($request->graphTemplateId, null, '') : null,
            type: $request->type,
        );


        $this->writeCommandRepository->update($originalCommand, $updatedCommand);
    }



    /**
     * @return CommandType[]
     */
    private function retrieveCommandTypesBasedOnContactRights(): array
    {
        if ($this->user->isAdmin()) {
            return [
                CommandType::Notification,
                CommandType::Check,
                CommandType::Miscellaneous,
                CommandType::Discovery,
            ];
        }
        $commandsTypes = [];

        if (
           $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW)
        ) {
            $commandsTypes[] = CommandType::Check;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW)
        ) {
            $commandsTypes[] = CommandType::Notification;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW)
        ) {
            $commandsTypes[] = CommandType::Miscellaneous;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW)
        ) {
            $commandsTypes[] = CommandType::Discovery;
        }

        return $commandsTypes;
    }
}