<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class ExceptionFormatterProcessorTest extends TestCase
{
    private ExceptionFormatterProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ExceptionFormatterProcessor();
    }

    public function testReplacesThrowableContextWithFormattedArray(): void
    {
        $record = $this->makeRecord(['exception' => new \RuntimeException('boom', 42)]);

        $processed = ($this->processor)($record);

        self::assertIsArray($processed->context['exception']);
        self::assertSame(\RuntimeException::class, $processed->context['exception']['type']);
        self::assertSame('boom', $processed->context['exception']['message']);
        self::assertSame(42, $processed->context['exception']['code']);
    }

    public function testLeavesContextUntouchedWhenNoExceptionKey(): void
    {
        $record = $this->makeRecord(['user_id' => 7]);

        $processed = ($this->processor)($record);

        self::assertSame(['user_id' => 7], $processed->context);
    }

    public function testLeavesContextUntouchedWhenExceptionAlreadyArray(): void
    {
        // Pin the no-op behaviour when an upstream layer (e.g. LoggingMiddleware
        // or an ad-hoc caller) has already serialised the exception —
        // re-running the formatter on an array would corrupt the context.
        $alreadyFormatted = ['type' => 'X', 'message' => 'pre-formatted', 'previous' => []];
        $record = $this->makeRecord(['exception' => $alreadyFormatted]);

        $processed = ($this->processor)($record);

        self::assertSame($alreadyFormatted, $processed->context['exception']);
    }

    public function testPreservesOtherContextKeys(): void
    {
        $record = $this->makeRecord([
            'user_id' => 7,
            'exception' => new \RuntimeException('boom'),
            'route' => '/api/foo',
        ]);

        $processed = ($this->processor)($record);

        self::assertSame(7, $processed->context['user_id']);
        self::assertSame('/api/foo', $processed->context['route']);
        self::assertIsArray($processed->context['exception']);
    }

    public function testReturnsRecordWithSameNonContextFields(): void
    {
        $record = $this->makeRecord(['exception' => new \RuntimeException('boom')]);

        $processed = ($this->processor)($record);

        self::assertSame($record->channel, $processed->channel);
        self::assertSame($record->message, $processed->message);
        self::assertSame($record->level, $processed->level);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function makeRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Error,
            message: 'something went wrong',
            context: $context,
        );
    }
}
