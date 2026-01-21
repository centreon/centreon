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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Command\Application\UseCase\GetCommand;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\Command;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Domain\Model\CommandMacro;

final class GetCommand
{
    use LoggerTrait;


    /**
     * Summary of __construct
     * @param ReadCommandRepositoryInterface $readCommandRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param GetCommandPresenterInterface $presenter
     * @param int $commandId
     */
    public function __invoke(GetCommandPresenterInterface $presenter, int $commandId): void
    {
        try {
            $command = $this->readCommandRepository->findById($commandId);
            if(! $command) {
                    $this->info('Command not found', ['command_id' => $commandId]);
                    $presenter->presentResponse(new NotFoundResponse('Command'));
                    return;
            }
            $commandTypes = $this->retrieveCommandTypesBasedOnContactRights();
            if ($commandTypes === [] || ! in_array($command->getType(), $commandTypes, true)) {
                $this->error(
                    "User doesn't have sufficient rights to see commands",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(CommandException::accessNotAllowed())
                );

                return;
            }



            $presenter->presentResponse($this->createResponse($command));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(CommandException::errorWhileRetrieving())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Command $command
     *
     * @throws \Throwable
     *
     *
     * @return GetCommandResponse
     */
    private function createResponse(Command $command): GetCommandResponse
    {
        $response = new GetCommandResponse();

        $response->id = $command->getId();
        $response->name = $command->getName();
        $response->type = $command->getType();
        $response->commandLine = $command->getCommandLine();
        $response->argumentExample = $command->getArgumentExample();
        $response->isShellEnabled = $command->isShellEnabled();
        $response->isActivated = $command->isActivated();
        $response->isLocked = $command->isLocked();
        $response->arguments = array_map(
            fn (Argument $argument) => [
                'name' => $argument->getName(),
                'description' => $argument->getDescription(),
            ],
            $command->getArguments(),
        );
        $response->macros = array_map(
            fn (CommandMacro $macro) => [
                'name' => $macro->getName(),
                'description' => $macro->getDescription(),
                'type' => $macro->getType(),
            ],
            $command->getMacros(),
        );
        $response->connector = $command->getConnector() !== null
            ? [
                'id' => $command->getConnector()->getId(),
                'name' => (string) $command->getConnector()->getName(),
            ]
            : null;
        $response->graphTemplate = $command->getGraphTemplate() !== null
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
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R)
            || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW)
        ) {
            $commandsTypes[] = CommandType::Check;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R)
            || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW)
        ) {
            $commandsTypes[] = CommandType::Notification;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R)
            || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW)
        ) {
            $commandsTypes[] = CommandType::Miscellaneous;
        }

        if (
            $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R)
            || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW)
        ) {
            $commandsTypes[] = CommandType::Discovery;
        }

        return $commandsTypes;
    }
}