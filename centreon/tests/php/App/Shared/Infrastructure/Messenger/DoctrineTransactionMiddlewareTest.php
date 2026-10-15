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
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Tests\App\Shared\Infrastructure\Messenger\Double\FailingStack;
use Tests\App\Shared\Infrastructure\Messenger\Double\PassThroughStack;

final class DoctrineTransactionMiddlewareTest extends TestCase
{
    private Connection&MockObject $connection;

    private DoctrineTransactionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->middleware = new DoctrineTransactionMiddleware($this->connection);
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
        $deadlock = new DeadlockException(
            new class ('Deadlock found when trying to get lock', 1213) extends \Exception implements \Doctrine\DBAL\Driver\Exception {
                public function getSQLState(): string
                {
                    return '40001';
                }
            },
            null,
        );

        $this->connection->method('rollBack')->willThrowException(
            new DriverException(
                new class ('There is no active transaction') extends \Exception implements \Doctrine\DBAL\Driver\Exception {
                    public function getSQLState(): ?string
                    {
                        return null;
                    }
                },
                null,
            )
        );

        $this->expectExceptionObject($deadlock);

        $this->middleware->handle(new Envelope(new \stdClass()), new FailingStack($deadlock));
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
}
