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
    public function testFormatCapturesCoreFields(): void
    {
        $exception = new \RuntimeException('boom', 42);

        $formatted = ExceptionFormatter::format($exception);

        self::assertSame(\RuntimeException::class, $formatted['type']);
        self::assertSame('boom', $formatted['message']);
        self::assertSame(42, $formatted['code']);
        self::assertSame($exception->getFile(), $formatted['file']);
        self::assertSame($exception->getLine(), $formatted['line']);
        self::assertSame([], $formatted['previous']);
    }

    public function testFormatFlattensPreviousChainInOrder(): void
    {
        $root = new \LogicException('root cause');
        $mid = new \RuntimeException('mid', 0, $root);
        $top = new \DomainException('top', 0, $mid);

        $formatted = ExceptionFormatter::format($top);

        self::assertSame(\DomainException::class, $formatted['type']);
        self::assertCount(2, $formatted['previous']);
        self::assertSame(\RuntimeException::class, $formatted['previous'][0]['type']);
        self::assertSame('mid', $formatted['previous'][0]['message']);
        self::assertSame(\LogicException::class, $formatted['previous'][1]['type']);
        self::assertSame('root cause', $formatted['previous'][1]['message']);
    }

    public function testLeavesCarryAnEmptyPreviousFieldToMatchTheRootShape(): void
    {
        // The shape itself (key set, key types) is locked by PHPDoc
        // and verified by PHPStan, so this test only pins the one
        // runtime invariant types cannot enforce: `previous` is always
        // empty on every leaf, including the truncation marker. This
        // is what lets a consumer iterate the tree with a single shape
        // and stop on each leaf without a special case.
        $exception = $this->chainOfDepth(25); // forces a truncation marker at the tail

        $formatted = ExceptionFormatter::format($exception);

        foreach ($formatted['previous'] as $entry) {
            self::assertSame([], $entry['previous']);
        }
    }

    public function testTraceIsCappedAtFifteenFramesAndSignalsTheRest(): void
    {
        // Build a trace deeper than the cap so both the truncation and the
        // omission marker are exercised. A top-level throw usually produces
        // a trace shorter than 15 frames, hence the recursion.
        $exception = $this->throwAtDepth(25);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(16, $formatted['trace'], '15 captured frames + 1 trailing omission marker');
        self::assertMatchesRegularExpression(
            '/^… \d+ frames omitted$/u',
            $formatted['trace'][15],
            'last entry must signal how many frames were dropped',
        );
    }

    public function testTraceShorterThanCapHasNoOmissionMarker(): void
    {
        // A short stack (top-level throw with no recursion) must not be
        // padded with a misleading "… 0 frames omitted" line.
        $exception = new \RuntimeException('boom');

        $formatted = ExceptionFormatter::format($exception);

        foreach ($formatted['trace'] as $frame) {
            self::assertStringNotContainsString('frames omitted', $frame);
        }
    }

    public function testPreviousChainIsCappedAtTwentyAndSignalsTruncation(): void
    {
        // A chain of 25 nested causes — well past the cap — exercises both
        // the truncation logic and the trailing marker. Without a cap, a
        // pathological chain (or a theoretical cycle) would produce an
        // unbounded log payload.
        $exception = $this->chainOfDepth(25);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(21, $formatted['previous'], '20 captured entries + 1 truncation marker');

        // First 20 entries are real exceptions
        for ($index = 0; $index < 20; $index++) {
            self::assertSame(\RuntimeException::class, $formatted['previous'][$index]['type']);
        }

        // Trailing entry is the marker — distinguishable by its sentinel type
        $marker = $formatted['previous'][20];
        self::assertSame('@truncated', $marker['type']);
        self::assertStringContainsString('truncated', $marker['message']);
        self::assertSame(0, $marker['code']);
        self::assertSame([], $marker['trace']);
    }

    public function testPreviousChainShorterThanCapHasNoTruncationMarker(): void
    {
        // 3 nested causes — well below the cap — must not have a marker
        // appended (which would mislead readers into thinking the chain
        // was truncated when it wasn't).
        $exception = $this->chainOfDepth(3);

        $formatted = ExceptionFormatter::format($exception);

        self::assertCount(3, $formatted['previous']);
        foreach ($formatted['previous'] as $entry) {
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

        self::assertSame(str_repeat('a', 1024) . '…[truncated]', $formatted['message']);
    }

    public function testMessageShorterThan1024CharsIsLeftUntouched(): void
    {
        $exception = new \RuntimeException('short message');

        $formatted = ExceptionFormatter::format($exception);

        self::assertSame('short message', $formatted['message']);
    }

    public function testTraceFrameFormatIncludesClassMethodFileLine(): void
    {
        $exception = new \RuntimeException('boom');

        $formatted = ExceptionFormatter::format($exception);

        self::assertNotEmpty($formatted['trace']);
        // Format pinned: "Class::method() at file:line" — log readers and
        // grep patterns rely on this exact shape.
        self::assertMatchesRegularExpression('/.+\(\) at .+:\d+$/', $formatted['trace'][0]);
    }

    /**
     * Builds a Throwable whose `previous` chain has $depth nested entries.
     * The deepest cause is a leaf RuntimeException, each parent wraps it
     * via the 3rd argument of the RuntimeException constructor.
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
