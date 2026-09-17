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

use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Messenger\LoggingMiddleware;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Tests\App\Shared\Infrastructure\Messenger\Double\FailingStack;
use Tests\App\Shared\Infrastructure\Messenger\Double\PassThroughStack;
use Tests\App\Shared\Infrastructure\Messenger\Double\SampleCommand;

#[Group('integration')]
final class LoggingMiddlewareIntegrationTest extends KernelTestCase
{
    private LoggingMiddleware $middleware;

    private TestHandler $testHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $logger = $container->get('monolog.logger');
        \assert($logger instanceof Logger);

        // Push a TestHandler on the app logger to capture records in-memory.
        $this->testHandler = new TestHandler();
        $logger->pushHandler($this->testHandler);

        $payloadNormalizer = $container->get(LogPayloadNormalizer::class);
        \assert($payloadNormalizer instanceof LogPayloadNormalizer);

        $this->middleware = new LoggingMiddleware($logger, $payloadNormalizer, new PayloadSanitizer());
    }

    public function testSuccessfulCommandProducesDispatchAndHandledInfoLogs(): void
    {
        $message = new SampleCommand('admin', 's3cret');
        $envelope = new Envelope($message, [new BusNameStamp('command.bus')]);

        $this->middleware->handle($envelope, new PassThroughStack($envelope));

        $infoRecords = $this->infoRecords();
        self::assertCount(2, $infoRecords);
        self::assertStringContainsString('Dispatching', $infoRecords[0]->message);
        self::assertStringContainsString('Handled', $infoRecords[1]->message);
        self::assertSame('command.bus', $infoRecords[0]->context['bus_type']);
        self::assertSame(SampleCommand::class, $infoRecords[0]->context['handler_message']);
    }

    public function testFailedCommandProducesCriticalLogWithExceptionChain(): void
    {
        $root = new \LogicException('root cause');
        $top = new \RuntimeException('handler failed', 0, $root);
        $envelope = new Envelope(new SampleCommand('admin', 's3cret'), [new BusNameStamp('command.bus')]);

        try {
            $this->middleware->handle($envelope, new FailingStack($top));
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
        }

        $criticalRecords = $this->criticalRecords();
        self::assertCount(1, $criticalRecords);
        self::assertStringContainsString('Failed', $criticalRecords[0]->message);

        $exception = $criticalRecords[0]->context['exception'];
        \assert(\is_array($exception));
        \assert(\is_array($exception['exceptions']));
        self::assertCount(2, $exception['exceptions'], 'root + 1 nested cause');
        \assert(\is_array($exception['exceptions'][0]));
        \assert(\is_array($exception['exceptions'][1]));
        self::assertSame(\RuntimeException::class, $exception['exceptions'][0]['type']);
        self::assertSame(\LogicException::class, $exception['exceptions'][1]['type']);
    }

    public function testSensitiveFieldsMaskedByRealNormalizer(): void
    {
        $message = new SampleCommand('admin', 's3cret');
        $envelope = new Envelope($message, [new BusNameStamp('command.bus')]);

        $this->middleware->handle($envelope, new PassThroughStack($envelope));

        $payload = $this->infoRecords()[0]->context['payload'];
        \assert(\is_array($payload));
        self::assertSame('admin', $payload['username']);
        self::assertSame('***', $payload['password']);
        self::assertSame('some description', $payload['description']);
    }

    public function testQueryBusNameIsPropagatedAsIs(): void
    {
        $envelope = new Envelope(new SampleCommand('admin', 'x'), [new BusNameStamp('query.bus')]);
        $this->middleware->handle($envelope, new PassThroughStack($envelope));

        self::assertSame('query.bus', $this->infoRecords()[0]->context['bus_type']);
    }

    /**
     * @return list<LogRecord>
     */
    private function infoRecords(): array
    {
        return array_values(
            array_filter(
                $this->testHandler->getRecords(),
                static fn (LogRecord $record): bool => $record->level === Level::Info,
            )
        );
    }

    /**
     * @return list<LogRecord>
     */
    private function criticalRecords(): array
    {
        return array_values(
            array_filter(
                $this->testHandler->getRecords(),
                static fn (LogRecord $record): bool => $record->level === Level::Critical,
            )
        );
    }
}
