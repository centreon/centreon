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

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Command\NonTransactionalCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class DoctrineTransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->getMessage() instanceof NonTransactionalCommand) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->connection->beginTransaction();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $this->connection->commit();

            return $envelope;
        } catch (\Throwable $th) {
            $this->rollBackKeepingCause();

            throw $th;
        }
    }

    /**
     * The server may already have ended the transaction (deadlock victim, lost connection): the driver
     * then refuses the rollback with "There is no active transaction". That failure must not replace
     * the exception that caused it, which is the one worth reporting.
     *
     * Only a DBAL failure is absorbed, so an Error raised by the rollback itself still surfaces. When this
     * middleware runs nested (a command dispatched inside the transaction of another one), the rollback goes
     * through a savepoint and DBAL only lowers its nesting level once that succeeds: an absorbed failure
     * leaves the connection believing a transaction is still open. A short-lived PHP-FPM request does not
     * care, but a long-running process (worker) would keep that stale level.
     */
    private function rollBackKeepingCause(): void
    {
        try {
            $this->connection->rollBack();
        } catch (DbalException $rollbackFailure) {
            $this->logger->debug(
                'The rollback failed, the exception that caused it is rethrown instead.',
                ['exception' => $rollbackFailure],
            );
        }
    }
}
