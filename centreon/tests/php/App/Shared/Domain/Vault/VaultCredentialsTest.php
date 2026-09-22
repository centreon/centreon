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

namespace Tests\App\Shared\Domain\Vault;

use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use PHPUnit\Framework\TestCase;

final class VaultCredentialsTest extends TestCase
{
    public function testFromArrayRoundTrip(): void
    {
        $map = ['_HOSTSNMPCOMMUNITY' => 'public', 'MACRO' => 'secret-value'];

        self::assertSame($map, VaultCredentials::fromArray($map)->toArray());
    }

    public function testEmpty(): void
    {
        self::assertTrue(VaultCredentials::fromArray([])->isEmpty());
        self::assertFalse(VaultCredentials::fromArray(['a' => 'b'])->isEmpty());
    }

    public function testWithIsImmutableAndAddsEntry(): void
    {
        $original = VaultCredentials::fromArray(['a' => '1']);
        $updated = $original->with('b', '2');

        self::assertSame(['a' => '1'], $original->toArray());
        self::assertSame(['a' => '1', 'b' => '2'], $updated->toArray());
    }

    public function testWithNormalizesVaultKey(): void
    {
        $credentials = VaultCredentials::fromArray([])->with(VaultKeyEnum::HostSnmpCommunity, 'public');

        self::assertSame(['_HOSTSNMPCOMMUNITY' => 'public'], $credentials->toArray());
    }

    public function testWithReplacesExistingKey(): void
    {
        $credentials = VaultCredentials::fromArray(['a' => '1'])->with('a', '2');

        self::assertSame(['a' => '2'], $credentials->toArray());
    }

    public function testPlaintextOnlyDropsEmptyAndAlreadyVaultedValues(): void
    {
        $credentials = VaultCredentials::fromArray([
            'plain' => 'to-store',
            'empty' => '',
            'already' => 'secret::vault::monitoring/hosts/uuid::already',
        ]);

        self::assertSame(['plain' => 'to-store'], $credentials->plaintextOnly()->toArray());
    }

    public function testPlaintextOnlyCanBecomeEmpty(): void
    {
        $credentials = VaultCredentials::fromArray([
            'empty' => '',
            'already' => 'secret::vault::x/uuid::already',
        ]);

        self::assertTrue($credentials->plaintextOnly()->isEmpty());
    }
}
