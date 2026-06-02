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

namespace Tests\App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\ExceptionFormatter;
use PHPUnit\Framework\TestCase;

final class ExceptionFormatterTest extends TestCase
{
    public function testFormatReturnsAnExceptionsListWithCoreFields(): void
    {
        $exception = new \RuntimeException('boom', 42);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(1, $formatted['exceptions']);

        $entry = $formatted['exceptions'][0];
        self::assertSame(\RuntimeException::class, $entry['type']);
        self::assertSame('boom', $entry['message']);
        self::assertSame(42, $entry['code']);
        self::assertSame($exception->getFile(), $entry['file']);
        self::assertSame($exception->getLine(), $entry['line']);
    }

    public function testFormatFlattensTheCauseChainInOrder(): void
    {
        $root = new \LogicException('root cause');
        $mid = new \RuntimeException('mid', 0, $root);
        $top = new \DomainException('top', 0, $mid);

        $formatted = ExceptionFormatter::format($top);

        self::assertCount(3, $formatted['exceptions']);
        self::assertSame(\DomainException::class, $formatted['exceptions'][0]['type']);
        self::assertSame('top', $formatted['exceptions'][0]['message']);
        self::assertSame(\RuntimeException::class, $formatted['exceptions'][1]['type']);
        self::assertSame('mid', $formatted['exceptions'][1]['message']);
        self::assertSame(\LogicException::class, $formatted['exceptions'][2]['type']);
        self::assertSame('root cause', $formatted['exceptions'][2]['message']);
    }

    public function testTraceIsCappedAtFifteenFramesAndSignalsTheRest(): void
    {
        // Build a trace deeper than the cap so both the truncation and the
        // omission marker are exercised. A top-level throw usually produces
        // a trace shorter than 15 frames, hence the recursion.
        $exception = $this->throwAtDepth(25);

        $formatted = ExceptionFormatter::format($exception);

        $trace = $formatted['exceptions'][0]['trace'];
        self::assertCount(16, $trace, '15 captured frames + 1 trailing omission marker');
        self::assertMatchesRegularExpression(
            '/^… \d+ frames omitted$/u',
            $trace[15],
            'last entry must signal how many frames were dropped',
        );
    }

    public function testTraceShorterThanCapHasNoOmissionMarker(): void
    {
        // A short stack (top-level throw with no recursion) must not be
        // padded with a misleading "… 0 frames omitted" line.
        $exception = new \RuntimeException('boom');

        $formatted = ExceptionFormatter::format($exception);

        foreach ($formatted['exceptions'][0]['trace'] as $frame) {
            self::assertStringNotContainsString('frames omitted', $frame);
        }
    }

    public function testCauseChainIsCappedAtTwentyAndSignalsTruncation(): void
    {
        // A chain of 25 nested causes — well past the cap — exercises both
        // the truncation logic and the trailing marker. Without a cap, a
        // pathological chain (or a theoretical cycle) would produce an
        // unbounded log payload.
        $exception = $this->chainOfDepth(25);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(21, $formatted['exceptions'], '20 captured entries + 1 truncation marker');

        // First 20 entries are real exceptions.
        for ($index = 0; $index < 20; $index++) {
            self::assertSame(\RuntimeException::class, $formatted['exceptions'][$index]['type']);
        }

        // Trailing entry is the marker — distinguishable by its sentinel type.
        $marker = $formatted['exceptions'][20];
        self::assertSame('@truncated', $marker['type']);
        self::assertStringContainsString('truncated', $marker['message']);
        self::assertSame(0, $marker['code']);
        self::assertSame([], $marker['trace']);
    }

    public function testCauseChainShorterThanCapHasNoTruncationMarker(): void
    {
        // 3 nested causes — well below the cap — must not have a marker
        // appended (which would mislead readers into thinking the chain
        // was truncated when it wasn't).
        $exception = $this->chainOfDepth(3);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(4, $formatted['exceptions'], 'root + 3 wrappers, no marker');
        foreach ($formatted['exceptions'] as $entry) {
            self::assertNotSame('@truncated', $entry['type']);
        }
    }

    public function testMessageLongerThan1024CharsIsTruncatedWithMarker(): void
    {
        // A PDOException carrying a multi-KB SQL fragment would otherwise
        // blow up the log row width — pin the same 1024-char ceiling we
        // already enforce on payload values.
        $longMessage = str_repeat('a', 2000);
        $exception = new \RuntimeException($longMessage);

        $formatted = ExceptionFormatter::format($exception);

        self::assertSame(str_repeat('a', 1024) . '…[truncated]', $formatted['exceptions'][0]['message']);
    }

    public function testMessageShorterThan1024CharsIsLeftUntouched(): void
    {
        $exception = new \RuntimeException('short message');

        $formatted = ExceptionFormatter::format($exception);

        self::assertSame('short message', $formatted['exceptions'][0]['message']);
    }

    public function testTraceFrameFormatIncludesClassMethodFileLine(): void
    {
        $exception = new \RuntimeException('boom');

        $formatted = ExceptionFormatter::format($exception);

        $trace = $formatted['exceptions'][0]['trace'];
        self::assertNotEmpty($trace);
        // Format pinned: "Class::method() at file:line" — log readers and
        // grep patterns rely on this exact shape.
        self::assertMatchesRegularExpression('/.+\(\) at .+:\d+$/', $trace[0]);
    }

    public function testFrameWithoutClassRendersWithoutLeadingDoubleColon(): void
    {
        // Closures and native callables (array_map, …) produce backtrace
        // frames without a `class` key. The renderer must emit just the
        // function name in that case — never `::App\{closure}()`.
        try {
            array_map(static function (int $value): int {
                throw new \RuntimeException('from closure inside array_map');
            }, [1]);
            self::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $thrown) {
            $formatted = ExceptionFormatter::format($thrown);
        }

        foreach ($formatted['exceptions'][0]['trace'] as $frame) {
            self::assertDoesNotMatchRegularExpression(
                '/^::/',
                $frame,
                sprintf('frame "%s" must not start with `::`', $frame),
            );
        }
    }

    /**
     * Builds a Throwable whose `previous` chain has $depth nested entries.
     * The deepest cause is a leaf RuntimeException; each parent wraps it
     * through the 3rd argument of the RuntimeException constructor.
     */
    private function chainOfDepth(int $depth): \Throwable
    {
        $current = new \RuntimeException('leaf');
        for ($index = 0; $index < $depth; $index++) {
            $current = new \RuntimeException("level {$index}", 0, $current);
        }

        return $current;
    }

    private function throwAtDepth(int $depth): \Throwable
    {
        if ($depth <= 0) {
            return new \RuntimeException('deep');
        }

        return $this->throwAtDepth($depth - 1);
    }
}
