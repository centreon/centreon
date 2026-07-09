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

namespace Tests\App\Shared\Infrastructure\Logging\Attribute;

use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\MethodSecretCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\MultiWordNestedCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\NestedInnerPayload;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\NestedPayloadCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\PlainCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\SecretsCommand;
use Tests\App\Shared\Infrastructure\Logging\Attribute\Fake\SensitiveValueObject;

final class SensitivityScannerTest extends TestCase
{
    protected function setUp(): void
    {
        SensitivityScanner::reset();
    }

    public function testReturnsEmptySensitiveListForClassWithoutAnnotations(): void
    {
        $scan = SensitivityScanner::scan(PlainCommand::class);

        self::assertSame([], $scan['sensitive']);
    }

    public function testCollectsSensitivePropertyNames(): void
    {
        $scan = SensitivityScanner::scan(SecretsCommand::class);

        // Recorded snake_cased to match the payload keys: `ssoTicket` → `sso_ticket`.
        self::assertSame(['passcode', 'sso_ticket'], $scan['sensitive']);
    }

    public function testCollectsAccessorKeyFromSensitiveMethod(): void
    {
        $scan = SensitivityScanner::scan(MethodSecretCommand::class);

        // Accessors mask the snake_cased key: `getApiToken` → `api_token`, `getSsoTicket` → `sso_ticket`,
        // `canManageUsers` → `manage_users`. `cancel()` keeps its full name (no uppercase boundary after `can`).
        self::assertSame(['api_token', 'sso_ticket', 'manage_users', 'cancel'], $scan['sensitive']);
    }

    public function testRecordsMultiWordSubClassKeyInSnakeCase(): void
    {
        $scan = SensitivityScanner::scan(MultiWordNestedCommand::class);

        // The subClasses key must be snake_cased to match the payload: `paymentCard` → `payment_card`.
        self::assertSame(['payment_card' => SensitiveValueObject::class], $scan['subClasses']);
    }

    public function testFlagsClassAnnotatedAsSensitive(): void
    {
        $scan = SensitivityScanner::scan(SensitiveValueObject::class);

        self::assertTrue($scan['classSensitive']);
    }

    public function testDoesNotFlagPlainClassAsSensitive(): void
    {
        $scan = SensitivityScanner::scan(PlainCommand::class);

        self::assertFalse($scan['classSensitive']);
    }

    public function testResolvesNonBuiltinPropertyTypesForRecursiveSanitisation(): void
    {
        $scan = SensitivityScanner::scan(NestedPayloadCommand::class);

        self::assertSame(['inner' => NestedInnerPayload::class], $scan['subClasses']);
    }

    public function testCachesScanResultBetweenCalls(): void
    {
        // Same class scanned twice — second call must return the same
        // array (the cache is the contract, otherwise every dispatch of
        // the same Command class re-walks its ReflectionClass).
        $first = SensitivityScanner::scan(SecretsCommand::class);
        $second = SensitivityScanner::scan(SecretsCommand::class);

        self::assertSame($first, $second);
    }
}
