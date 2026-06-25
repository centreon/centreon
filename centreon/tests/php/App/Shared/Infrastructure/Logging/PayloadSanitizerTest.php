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
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\MultiWordNestedCommand;
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
        // `sso_ticket` matches no denylist keyword, so masking it exercises the attribute path alone.
        $sanitised = $this->sanitizer->sanitize(
            ['passcode' => '482031', 'sso_ticket' => 'st-abc', 'user_id' => 7],
            SecretsCommand::class,
        );

        self::assertSame(['passcode' => '***', 'sso_ticket' => '***', 'user_id' => 7], $sanitised);
    }

    public function testRecursesIntoTypedSubObjectsToHonourNestedAnnotations(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            ['inner' => ['passcode' => '482031', 'label' => 'two-factor']],
            NestedPayloadCommand::class,
        );

        self::assertSame(['inner' => ['passcode' => '***', 'label' => 'two-factor']], $sanitised);
    }

    public function testMasksTheAccessorKeyExposedByASensitiveMethod(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            ['api_token' => 'tok-123', 'login' => 'admin'],
            MethodSecretCommand::class,
        );

        self::assertSame(['api_token' => '***', 'login' => 'admin'], $sanitised);
    }

    public function testMasksNonKeywordAccessorKeyExposedByASensitiveMethod(): void
    {
        // `sso_ticket` matches no denylist keyword, so this isolates the getter's attribute net from the keyword net.
        $sanitised = $this->sanitizer->sanitize(
            ['sso_ticket' => 'st-abc', 'login' => 'admin'],
            MethodSecretCommand::class,
        );

        self::assertSame(['sso_ticket' => '***', 'login' => 'admin'], $sanitised);
    }

    public function testMasksWholeValueTypedAsASensitiveClass(): void
    {
        $sanitised = $this->sanitizer->sanitize(
            [
                'card' => ['number' => 'card-number-test', 'holder' => 'admin'],
                'label' => 'default card',
            ],
            CardHolderCommand::class,
        );

        // The `card` property is typed as a `#[Sensitive]` class: the
        // whole sub-payload is masked, never descended into.
        self::assertSame(['card' => '***', 'label' => 'default card'], $sanitised);
    }

    public function testMasksMultiWordValueTypedAsASensitiveClass(): void
    {
        // Wholesale mask via the multi-word snake_cased `subClasses` key (`payment_card`).
        $sanitised = $this->sanitizer->sanitize(
            [
                'payment_card' => ['number' => 'card-number-test', 'holder' => 'admin'],
                'label' => 'default card',
            ],
            MultiWordNestedCommand::class,
        );

        self::assertSame(['payment_card' => '***', 'label' => 'default card'], $sanitised);
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

    public function testMasksSensitiveQueryParametersInUrlStrings(): void
    {
        // The request URL (e.g. WebProcessor's `extra.url`) is a plain string,
        // not a key/value pair, so a secret passed as a query parameter is not
        // caught by key masking. Parameters whose name matches the denylist are
        // redacted in place; the path and the other parameters are preserved.
        $sanitised = $this->sanitizer->sanitize([
            'url' => '/centreon/api/latest/login?useralias=admin&token=ABC123&autologin=1',
        ]);

        self::assertSame(
            ['url' => '/centreon/api/latest/login?useralias=admin&token=***&autologin=1'],
            $sanitised,
        );
    }

    public function testLeavesUrlStringsWithoutSensitiveQueryParametersUntouched(): void
    {
        $sanitised = $this->sanitizer->sanitize(['url' => '/monitoring/resources?page=2&limit=30']);

        self::assertSame(['url' => '/monitoring/resources?page=2&limit=30'], $sanitised);
    }

    public function testStillRedactsUrlSecretsWhenKeywordKeyMaskingIsDisabled(): void
    {
        // `extra` is sanitised with keyword-key masking OFF (its keys are set by
        // platform processors and must stay readable): `token` keeps its audit
        // payload, but a secret inside a URL query is still redacted.
        $sanitised = $this->sanitizer->sanitize(
            [
                'token' => ['username' => 'admin', 'role' => 'ROLE_ADMIN'],
                'url' => '/login?token=ABC123',
            ],
            maskKeywordKeys: false,
        );

        self::assertSame(
            [
                'token' => ['username' => 'admin', 'role' => 'ROLE_ADMIN'],
                'url' => '/login?token=***',
            ],
            $sanitised,
        );
    }

    public function testPropagatesKeywordKeyMaskingFlagThroughRecursion(): void
    {
        // With keyword-key masking off, a denylisted key stays in clear at any
        // depth — the flag must survive the recursive descent, not just the top
        // level. URL-query masking still applies regardless of the flag.
        $sanitised = $this->sanitizer->sanitize(
            ['outer' => ['token' => 'raw', 'url' => '/x?secret=s']],
            maskKeywordKeys: false,
        );

        self::assertSame(
            ['outer' => ['token' => 'raw', 'url' => '/x?secret=***']],
            $sanitised,
        );
    }

    public function testMasksSensitiveUrlQueryParametersInStringableValues(): void
    {
        $url = new class () implements \Stringable {
            public function __toString(): string
            {
                return '/login?useralias=admin&token=ABC123';
            }
        };

        $sanitised = $this->sanitizer->sanitize(['ref' => $url]);

        self::assertSame(['ref' => '/login?useralias=admin&token=***'], $sanitised);
    }

    public function testLeavesQueryEdgeCasesUntouched(): void
    {
        // Empty query and a parameter with no `=` are no-ops.
        self::assertSame(['u' => '/x?'], $this->sanitizer->sanitize(['u' => '/x?']));
        self::assertSame(['u' => '/x?token'], $this->sanitizer->sanitize(['u' => '/x?token']));
    }

    public function testMatchesQueryParameterNamesCaseInsensitivelyAndUrlDecoded(): void
    {
        // The parameter name is lowercased and percent-decoded before matching,
        // so `Token` and `to%6Ben` are both recognised; only the value is replaced.
        self::assertSame(['u' => '/x?Token=***'], $this->sanitizer->sanitize(['u' => '/x?Token=ABC']));
        self::assertSame(['u' => '/x?to%6Ben=***'], $this->sanitizer->sanitize(['u' => '/x?to%6Ben=ABC']));
    }

    public function testDoesNotMaskSecretsOutsideTheQueryComponent(): void
    {
        // Documented scope limits (best-effort, query component only): a secret in
        // the PATH, or nested inside another parameter's VALUE after a second `?`,
        // is NOT masked. Pinned so widening the parser stays a conscious choice.
        self::assertSame(
            ['u' => '/reset/TOKEN-ABC/confirm'],
            $this->sanitizer->sanitize(['u' => '/reset/TOKEN-ABC/confirm']),
        );
        self::assertSame(
            ['u' => '/cb?next=/r?token=ABC&page=2'],
            $this->sanitizer->sanitize(['u' => '/cb?next=/r?token=ABC&page=2']),
        );
    }

    public function testMasksTheAdditionalKeywordAliases(): void
    {
        $sanitised = $this->sanitizer->sanitize([
            'apikey' => 'a',
            'pwd' => 'b',
            'signature' => 'c',
            'session_id' => 'd',
            'access_key' => 'e',
            'login' => 'admin',
        ]);

        self::assertSame(
            [
                'apikey' => '***',
                'pwd' => '***',
                'signature' => '***',
                'session_id' => '***',
                'access_key' => '***',
                'login' => 'admin',
            ],
            $sanitised,
        );
    }
}
