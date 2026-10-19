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

namespace Tests\App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Infrastructure\Messenger\DoctrineTransactionMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Tests\App\Shared\Infrastructure\Messenger\Double\FailingStack;
use Tests\App\Shared\Infrastructure\Messenger\Double\PassThroughStack;

final class DoctrineTransactionMiddlewareTest extends TestCase
{
    private Connection&MockObject $connection;

    private LoggerInterface&MockObject $logger;

    private DoctrineTransactionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->middleware = new DoctrineTransactionMiddleware($this->connection, $this->logger);
    }

    public function testItCommitsWhenTheHandlerSucceeds(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::once())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');

        $result = $this->middleware->handle($envelope, new PassThroughStack($envelope));

        self::assertSame($envelope, $result);
    }

    public function testItRollsBackAndRethrowsWhenTheHandlerFails(): void
    {
        $failure = new \RuntimeException('handler failed');

        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::never())->method('commit');
        $this->connection->expects(self::once())->method('rollBack');

        $this->expectExceptionObject($failure);

        $this->middleware->handle(new Envelope(new \stdClass()), new FailingStack($failure));
    }

    public function testItKeepsTheOriginalExceptionWhenTheServerAlreadyEndedTheTransaction(): void
    {
        // A deadlock victim is rolled back by the server: the driver then refuses our own rollback with
        // "There is no active transaction", which must not replace the deadlock.
        $deadlock = $this->deadlock();

        $this->connection->expects(self::once())->method('rollBack')->willThrowException(
            new DriverException(new class ('There is no active transaction') extends AbstractException {
            }, null)
        );
        $this->logger->expects(self::once())->method('debug');

        $this->expectExceptionObject($deadlock);

        $this->middleware->handle(new Envelope(new \stdClass()), new FailingStack($deadlock));
    }

    public function testItRollsBackAndRethrowsTheCommitFailure(): void
    {
        // A commit refused by the server (deadlock victim at commit time) leaves nothing to roll back.
        $deadlock = $this->deadlock();

        $this->connection->method('commit')->willThrowException($deadlock);
        $this->connection->expects(self::once())->method('rollBack')->willThrowException(
            new DriverException(new class ('There is no active transaction') extends AbstractException {
            }, null)
        );

        $this->expectExceptionObject($deadlock);

        $envelope = new Envelope(new \stdClass());
        $this->middleware->handle($envelope, new PassThroughStack($envelope));
    }

    public function testItKeepsTheOriginalExceptionWhateverTheDbalReasonOfTheRollbackFailure(): void
    {
        $failure = new \RuntimeException('handler failed');

        $this->connection->expects(self::once())->method('rollBack')->willThrowException(
            new DriverException(new class ('Lost connection to server during query') extends AbstractException {
            }, null)
        );
        $this->logger->expects(self::once())->method('debug');

        $this->expectExceptionObject($failure);

        $this->middleware->handle(new Envelope(new \stdClass()), new FailingStack($failure));
    }

    public function testItDoesNotAbsorbAnErrorRaisedByTheRollback(): void
    {
        // Only a DBAL failure is expected from a rollback: anything else is a bug that must surface.
        $this->connection->method('rollBack')->willThrowException(new \TypeError('unexpected'));

        $this->expectException(\TypeError::class);

        $this->middleware->handle(new Envelope(new \stdClass()), new FailingStack(new \RuntimeException('handler failed')));
    }

    public function testItDoesNotOpenATransactionForANonTransactionalCommand(): void
    {
        $envelope = new Envelope(new class () implements NonTransactionalCommand {
        });

        $this->connection->expects(self::never())->method('beginTransaction');
        $this->connection->expects(self::never())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');

        $this->middleware->handle($envelope, new PassThroughStack($envelope));
    }

    private function deadlock(): DeadlockException
    {
        return new DeadlockException(
            new class ('Deadlock found when trying to get lock', '40001', 1213) extends AbstractException {
            },
            null,
        );
    }
}
