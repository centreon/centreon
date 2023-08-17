<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Application\UseCase\FindCommands;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;

final class FindCommands
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ContactInterface $contact,
    ) {
    }

    /**
     * @param FindCommandsPresenterInterface $presenter
     */
    public function __invoke(FindCommandsPresenterInterface $presenter): void
    {
        try {
            $commandTypes = $this->retrieveCommandTypesBasedOnContactRights();
            if ($commandTypes === []) {
                $this->error(
                    "User doesn't have sufficient rights to see commands",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(CommandException::accessNotAllowed())
                );

                return;
            }

            $commands = $this->readCommandRepository->findByRequestParameterAndTypes(
                $this->requestParameters,
                $commandTypes
            );

            $presenter->presentResponse($this->createResponse($commands));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(CommandException::errorWhileSearching($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Command[] $commands
     *
     * @return FindCommandsResponse
     */
    private function createResponse(array $commands): FindCommandsResponse
    {
        $response = new FindCommandsResponse();

        foreach ($commands as $command) {
            $commandDto = new CommandDto();
            $commandDto->id = $command->getId();
            $commandDto->name = $command->getName();
            $commandDto->type = $command->getType();
            $commandDto->commandLine = $command->getCommandLine();
            $commandDto->isShellEnabled = $command->isShellEnabled();
            $commandDto->isActivated = $command->isActivated();
            $commandDto->isLocked = $command->isLocked();
            $response->commands[] = $commandDto;
        }

        return $response;
    }

    /**
     * @return CommandType[]
     */
    private function retrieveCommandTypesBasedOnContactRights(): array
    {
        if ($this->contact->isAdmin()) {
            return [
                CommandType::Notification,
                CommandType::Check,
                CommandType::Miscellaneous,
                CommandType::Discovery,
            ];
        }
        $commandsTypes = [];

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW)
        ) {
            $commandsTypes[] = CommandType::Check;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW)
        ) {
            $commandsTypes[] = CommandType::Notification;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW)
        ) {
            $commandsTypes[] = CommandType::Miscellaneous;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW)
        ) {
            $commandsTypes[] = CommandType::Discovery;
        }

        return $commandsTypes;
    }
}
