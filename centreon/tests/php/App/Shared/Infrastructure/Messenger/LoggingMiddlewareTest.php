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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class LoggingMiddlewareTest extends TestCase
{
    private LoggerSpy $logger;

    private LoggingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->logger = new LoggerSpy();
        $this->middleware = new LoggingMiddleware(
            $this->logger,
            new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter()),
            new PayloadSanitizer(),
        );
    }

    public function testCommandSuccessLogsInfoTwiceAndNoError(): void
    {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);
        $stack = $this->createPassThroughStack($envelope);

        $result = $this->middleware->handle($envelope, $stack);

        self::assertSame($envelope->getMessage(), $result->getMessage());
        self::assertCount(2, $this->logger->infoMessages);
        self::assertCount(0, $this->logger->errorMessages);
        self::assertStringContainsString('Dispatching', $this->logger->infoMessages[0]['message']);
        self::assertSame('command.bus', $this->logger->infoMessages[0]['context']['bus_type']);
        self::assertStringContainsString('Handled', $this->logger->infoMessages[1]['message']);
        self::assertSame('command.bus', $this->logger->infoMessages[1]['context']['bus_type']);
        // payload is logged once, on Dispatching; Handled omits it (same
        // dispatch_id pairs the two), avoiding duplicate payload noise.
        self::assertArrayHasKey('payload', $this->logger->infoMessages[0]['context']);
        self::assertArrayNotHasKey('payload', $this->logger->infoMessages[1]['context']);
    }

    public function testUnexpectedFailureLogsCriticalWithExceptionTypeAndRethrows(): void
    {
        $exception = new \RuntimeException('Something broke');
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);
        $stack = $this->createFailingStack($exception);

        try {
            $this->middleware->handle($envelope, $stack);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertCount(1, $this->logger->infoMessages);
        self::assertCount(0, $this->logger->warningMessages);
        self::assertCount(1, $this->logger->criticalMessages);

        $criticalContext = $this->logger->criticalMessages[0]['context'];
        $exception = $criticalContext['exception'];
        \assert(\is_array($exception));
        \assert(\is_array($exception['exceptions']));
        self::assertCount(1, $exception['exceptions']);
        \assert(\is_array($exception['exceptions'][0]));
        self::assertSame(\RuntimeException::class, $exception['exceptions'][0]['type']);
        self::assertSame('Something broke', $exception['exceptions'][0]['message']);
    }

    public function testDomainValidationFailureLogsWarningNotCritical(): void
    {
        // A value-object constructor rejection (\InvalidArgumentException, the
        // parent of Centreon's AssertionException) is expected client input
        // mapped to a 4xx — it must stay at warning, never escalate to the
        // critical level reserved for unexpected server-side failures.
        $exception = new \InvalidArgumentException('Service category name cannot be empty');
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);
        $stack = $this->createFailingStack($exception);

        try {
            $this->middleware->handle($envelope, $stack);
            self::fail('Expected exception was not thrown.');
        } catch (\InvalidArgumentException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertCount(0, $this->logger->criticalMessages);
        self::assertCount(1, $this->logger->warningMessages);

        $warningContext = $this->logger->warningMessages[0]['context'];
        $formatted = $warningContext['exception'];
        \assert(\is_array($formatted));
        \assert(\is_array($formatted['exceptions']));
        \assert(\is_array($formatted['exceptions'][0]));
        self::assertSame(\InvalidArgumentException::class, $formatted['exceptions'][0]['type']);
    }

    public function testExceptionChainCapturesAllLevels(): void
    {
        $root = new \LogicException('root cause');
        $mid = new \RuntimeException('mid', 0, $root);
        $top = new \DomainException('top', 0, $mid);

        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);

        try {
            $this->middleware->handle($envelope, $this->createFailingStack($top));
            self::fail('Expected exception was not thrown.');
        } catch (\DomainException) {
        }

        self::assertCount(1, $this->logger->criticalMessages);
        $exception = $this->logger->criticalMessages[0]['context']['exception'];
        \assert(\is_array($exception));
        \assert(\is_array($exception['exceptions']));
        self::assertCount(3, $exception['exceptions'], 'root + 2 nested causes');
        \assert(\is_array($exception['exceptions'][0]));
        \assert(\is_array($exception['exceptions'][1]));
        \assert(\is_array($exception['exceptions'][2]));
        self::assertSame(\DomainException::class, $exception['exceptions'][0]['type']);
        self::assertSame(\RuntimeException::class, $exception['exceptions'][1]['type']);
        self::assertSame(\LogicException::class, $exception['exceptions'][2]['type']);
    }

    public function testBusNameStampValueIsPropagatedAsIs(): void
    {
        // The middleware no longer classifies bus names — the raw value
        // from the envelope's BusNameStamp lands in bus_type as-is. Pin
        // it on a non-default bus (query.bus) so a regression to silent
        // re-labelling would fail loudly.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('query.bus')]);
        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));

        self::assertSame('query.bus', $this->logger->infoMessages[0]['context']['bus_type']);
    }

    public function testEnvelopeWithoutBusNameStampLogsAsUnknown(): void
    {
        // No BusNameStamp on the envelope — happens on direct dispatches
        // outside the bus convention (CLI ad-hoc, tests, custom transport
        // re-creating the envelope). The middleware must not deref a null
        // stamp; it falls back to the literal 'unknown' so the log keeps
        // a usable bus_type field.
        $envelope = new Envelope(new \stdClass());
        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));

        self::assertSame('unknown', $this->logger->infoMessages[0]['context']['bus_type']);
    }

    public function testCyclicPayloadIsLoggedWithoutHanging(): void
    {
        // Regression for the unbounded-recursion freeze: a message whose object
        // graph references itself must be normalised, masked and logged without
        // hanging the dispatch. The bus call returns and the payload carries a
        // bounded recursion marker instead of spinning the normalizer.
        $message = new class () {
            public ?object $self = null;

            public string $name = 'cyclic';
        };
        $message->self = $message;

        $envelope = new Envelope($message, [new BusNameStamp('command.bus')]);
        $result = $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));

        self::assertSame($message, $result->getMessage());
        $payload = $this->logger->infoMessages[0]['context']['payload'];
        \assert(\is_array($payload));
        self::assertSame('cyclic', $payload['name']);
        self::assertIsString($payload['self']);
        self::assertStringContainsString('(already logged)', $payload['self']);
    }

    public function testDurationMsIsPresentOnHandledAndFailureLogs(): void
    {
        // Pin the contract: `duration_ms` is emitted on Handled (success)
        // and Failed (critical), but NOT on Dispatching (which is the t0
        // reference). The value is non-negative and finite — exact figures
        // can't be asserted in a unit test (it measures real elapsed time
        // through a stub stack), so we pin shape and contract, not value.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);

        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));

        self::assertArrayNotHasKey('duration_ms', $this->logger->infoMessages[0]['context']);
        self::assertArrayHasKey('duration_ms', $this->logger->infoMessages[1]['context']);
        self::assertIsFloat($this->logger->infoMessages[1]['context']['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $this->logger->infoMessages[1]['context']['duration_ms']);

        try {
            $this->middleware->handle($envelope, $this->createFailingStack(new \RuntimeException('boom')));
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
        }

        self::assertArrayHasKey('duration_ms', $this->logger->criticalMessages[0]['context']);
        self::assertIsFloat($this->logger->criticalMessages[0]['context']['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $this->logger->criticalMessages[0]['context']['duration_ms']);
    }

    public function testHandledLogListsHandlerNamesFromHandledStamps(): void
    {
        // Pin the contract: the Handled info log carries a `handlers` list
        // populated from HandledStamp::getHandlerName(). Useful on an event
        // bus where multiple handlers can run for one dispatch — the
        // dispatch's `handler_message` (the message class) does not tell
        // which subscriber actually fired. Order follows envelope stamp order.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('event.bus')]);
        $handled = $envelope
            ->with(new HandledStamp('result-a', 'App\\Foo\\Handler::onSomething'))
            ->with(new HandledStamp('result-b', 'App\\Bar\\Handler::onSomething'));
        $stack = $this->createPassThroughStack($handled);

        $this->middleware->handle($envelope, $stack);

        self::assertSame(
            ['App\\Foo\\Handler::onSomething', 'App\\Bar\\Handler::onSomething'],
            $this->logger->infoMessages[1]['context']['handlers'],
        );
    }

    public function testHandledLogHasEmptyHandlersListWhenNoStampPresent(): void
    {
        // When no handler ran (e.g. a transport-only middleware short-
        // circuited), the `handlers` key must still be present and be an
        // empty list — so consumers don't have to special-case a missing
        // field.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);

        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));

        self::assertSame([], $this->logger->infoMessages[1]['context']['handlers']);
    }

    public function testDispatchIdIsSharedAcrossTheThreeLogsOfOneHandle(): void
    {
        // Pin the pair-matching contract: the three logs emitted on success
        // (Dispatching / Handled) — or the two on failure (Dispatching /
        // Failed) — carry the SAME `dispatch_id` so an operator can join
        // them in production log search even when other bus traffic is
        // interleaved. Different handle() calls must produce different ids.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);

        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));
        $firstDispatchId = $this->logger->infoMessages[0]['context']['dispatch_id'];
        self::assertIsString($firstDispatchId);
        self::assertNotSame('', $firstDispatchId);
        self::assertSame($firstDispatchId, $this->logger->infoMessages[1]['context']['dispatch_id']);

        $this->middleware->handle($envelope, $this->createPassThroughStack($envelope));
        $secondDispatchId = $this->logger->infoMessages[2]['context']['dispatch_id'];
        self::assertNotSame($firstDispatchId, $secondDispatchId);
    }

    public function testDispatchIdIsAlsoPresentOnTheFailureLog(): void
    {
        // Same contract on the failure path: the failed log carries the same
        // dispatch_id as its companion Dispatching log, so a 500 stacktrace
        // can be matched back to its payload without ambiguity.
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('command.bus')]);

        try {
            $this->middleware->handle($envelope, $this->createFailingStack(new \RuntimeException('boom')));
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException) {
        }

        $dispatchId = $this->logger->infoMessages[0]['context']['dispatch_id'];
        self::assertIsString($dispatchId);
        self::assertSame($dispatchId, $this->logger->criticalMessages[0]['context']['dispatch_id']);
    }

    private function createPassThroughStack(Envelope $envelope): StackInterface
    {
        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willReturn($envelope);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    private function createFailingStack(\Throwable $exception): StackInterface
    {
        $next = $this->createStub(MiddlewareInterface::class);
        $next->method('handle')->willThrowException($exception);

        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
