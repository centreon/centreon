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

use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\CardHolderCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\MethodSecretCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\NestedPayloadCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\SecretsCommand;

final class PayloadSanitizerTest extends TestCase
{
    private PayloadSanitizer $sanitizer;

    protected function setUp(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();
        $this->sanitizer = new PayloadSanitizer();
    }

    public function testMasksKeywordKeysOfRawArraysWithoutAContextClass(): void
    {
        // No contextClass: there is no class to scan for #[Sensitive].
        // As the cross-channel net, the sanitiser still masks raw array
        // keys that match the shared keyword denylist, so an ad-hoc
        // `$logger->error('m', ['password' => ...])` cannot leak.
        $sanitised = $this->sanitizer->sanitize([
            'login' => 'admin',
            'password' => 'secret123',
            'api_key' => 'abc',
        ]);

        self::assertSame(
            ['login' => 'admin', 'password' => '***', 'api_key' => '***'],
            $sanitised,
        );
    }

    public function testMasksOnlyTheAnnotatedPropertiesOfTheContextClass(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            ['passcode' => '482031', 'ssoTicket' => 'st-abc', 'userId' => 7],
            0,
            SecretsCommand::class,
        );

        self::assertSame(['passcode' => '***', 'ssoTicket' => '***', 'userId' => 7], $sanitised);
    }

    public function testRecursesIntoTypedSubObjectsToHonourNestedAnnotations(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            ['inner' => ['passcode' => '482031', 'label' => 'two-factor']],
            0,
            NestedPayloadCommand::class,
        );

        self::assertSame(['inner' => ['passcode' => '***', 'label' => 'two-factor']], $sanitised);
    }

    public function testMasksTheAccessorKeyExposedByASensitiveMethod(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            ['apiToken' => 'tok-123', 'login' => 'admin'],
            0,
            MethodSecretCommand::class,
        );

        self::assertSame(['apiToken' => '***', 'login' => 'admin'], $sanitised);
    }

    public function testMasksWholeValueTypedAsASensitiveClass(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            [
                'card' => ['number' => 'card-number-test', 'holder' => 'admin'],
                'label' => 'default card',
            ],
            0,
            CardHolderCommand::class,
        );

        // The `card` property is typed as a `#[Sensitive]` class: the
        // whole sub-payload is masked, never descended into.
        self::assertSame(['card' => '***', 'label' => 'default card'], $sanitised);
    }

    public function testReturnsThrowableUntouchedSoExceptionFormatterCanStructureIt(): void
    {
        // Pin the contract relied on by ExceptionFormatterProcessor:
        // a raw Throwable in the context survives sanitisation as-is,
        // otherwise the downstream processor would see a placeholder
        // string and could not unwrap the chain.
        $exception = new \RuntimeException('boom');

        $sanitised = $this->sanitizer->sanitize(['exception' => $exception, 'context_id' => 7]);

        \assert(\is_array($sanitised));
        self::assertSame($exception, $sanitised['exception']);
        self::assertSame(7, $sanitised['context_id']);
    }

    public function testTruncatesStringsLongerThanCap(): void
    {
        $longString = str_repeat('a', 2000);

        $sanitised = $this->sanitizer->sanitize(['note' => $longString]);

        \assert(\is_array($sanitised));
        self::assertSame(str_repeat('a', 1024) . '…[truncated]', $sanitised['note']);
    }

    public function testCapsRecursionDepth(): void
    {
        // 4 levels deep: the cap is 3, so the innermost array must be
        // collapsed to the `{…}` marker.
        $nested = ['l1' => ['l2' => ['l3' => ['l4' => 'reached']]]];

        $sanitised = $this->sanitizer->sanitize($nested);

        \assert(\is_array($sanitised));
        \assert(\is_array($sanitised['l1']));
        \assert(\is_array($sanitised['l1']['l2']));
        self::assertSame('{…}', $sanitised['l1']['l2']['l3']);
    }

    public function testRendersBackedEnumByValue(): void
    {
        $sanitised = $this->sanitizer->sanitize(['status' => Double\BackedColour::Red]);

        \assert(\is_array($sanitised));
        self::assertSame('red', $sanitised['status']);
    }
}
