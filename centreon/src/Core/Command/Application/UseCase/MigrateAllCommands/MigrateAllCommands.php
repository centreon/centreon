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

declare(strict_types = 1);

namespace Core\Command\Application\UseCase\MigrateAllCommands;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\NewCommand;
use Psr\Log\LoggerInterface;

final class MigrateAllCommands
{
    private MigrateAllCommandsResponse $response;

    public function __construct(
        readonly private ReadCommandRepositoryInterface $readCommandRepository,
        readonly private WriteCommandRepositoryInterface $writeCommandRepository,
        readonly private LoggerInterface $logger,
    ) {
        $this->response = new MigrateAllCommandsResponse();
    }

    public function __invoke(MigrateAllCommandsPresenterInterface $presenter): void
    {
        try {
            $commands = $this->readCommandRepository->findAll();

            $this->migrateCommands($commands, $this->response);

            $presenter->presentResponse($this->response);
        } catch (\Throwable $ex) {
            $this->logger->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        }
    }

    /**
     * @param \Iterator<int,Command>&\Countable $commands
     * @param MigrateAllCommandsResponse $response
     */
    private function migrateCommands(\Iterator&\Countable $commands, MigrateAllCommandsResponse $response): void
    {
        $response->results = new class(
            $commands,
            $this->writeCommandRepository,
            $this->logger,
        ) implements \Iterator, \Countable
        {
            /**
             * @param \Iterator<int,Command>&\Countable $commands
             * @param WriteCommandRepositoryInterface $writeCommandRepository
             * @param LoggerInterface $logger
             */
            public function __construct(
                readonly private \Iterator&\Countable $commands,
                readonly private WriteCommandRepositoryInterface $writeCommandRepository,
                readonly private LoggerInterface $logger
            ) {
            }

            /**
             * @return CommandRecordedDto|MigrationErrorDto
             */
            public function current(): CommandRecordedDto|MigrationErrorDto
            {
                /** @var Command $command */
                $command = $this->commands->current();
                try {
                    if ($command->isLocked() || ! $command->isActivated()) {
                        $this->logger->debug(
                            'Command disabled or locked, skip migration',
                            ['command_id' => $command->getId()]
                        );

                        throw new \Exception('Command disabled or locked');
                    }
                    $targetNewCommandId = $this->writeCommandRepository->add(NewCommand::createFromCommand($command));
                    $status = new CommandRecordedDto();
                    $status->targetId = $targetNewCommandId;
                    $status->sourceId = $command->getId();
                    $status->name = $command->getName();

                    return $status;
                } catch (\Throwable $ex) {
                    $status = new MigrationErrorDto();
                    $status->id = $command->getId();
                    $status->name = $command->getName();
                    $status->reason = $ex->getMessage();

                    return $status;
                }
            }

            public function next(): void
            {
                $this->commands->next();
            }

            public function key(): int
            {
                return $this->commands->key();
            }

            public function valid(): bool
            {
                return $this->commands->key() < $this->commands->count();
            }

            public function rewind(): void
            {
                $this->commands->rewind();
            }

            public function count(): int
            {
                return $this->commands->count();
            }
        };
    }
}
