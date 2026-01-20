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

namespace Core\Command\Application\UseCase\DeleteCommand;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Centreon\Domain\Contact\Contact;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Exception\CommandException;

final class DeleteCommand
{
    use LoggerTrait;

    /**
     * Summary of __construct
     * @param ReadCommandRepositoryInterface $readCommandRepository
     * @param WriteCommandRepositoryInterface $writeCommandRepository
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly WriteCommandRepositoryInterface $writeCommandRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param int $commandId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $commandId, PresenterInterface $presenter): void
    {
        try {

            $command = $this->readCommandRepository->findById($commandId);
            if(! $command) {
                    $this->info('Command not found', ['command_id' => $commandId]);
                    $presenter->setResponseStatus(new NotFoundResponse('Command'));
                    return;
            }

            $commandTypes = $this->retrieveCommandTypesBasedOnContactRights();
            if ($commandTypes === [] || ! in_array($command->getType(), $commandTypes, true)) {
                $this->error(
                    "User doesn't have sufficient rights to delete commands",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(CommandException::deleteNotAllowed())
                );

                return;
            }




            $this->info(message: "Delete command #{$commandId}");
            $this->writeCommandRepository->delete($commandId);

            $presenter->setResponseStatus(new NoContentResponse());
            $this->info(
                'Command deleted',
                [
                    'command_id' => $commandId,
                    'user_id' => $this->user->getId(),
                ]
            );
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(CommandException::errorWhileDeletingCommand()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
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