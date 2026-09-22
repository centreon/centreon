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

use App\Shared\Domain\Vault\VaultKeyEnum;
use PHPUnit\Framework\TestCase;

/**
 * Pins the enum's literal values so an accidental App-side edit fails loudly.
 *
 * These literals are transcribed by hand from Core
 * (`Core\Security\Vault\Domain\Model\VaultConfiguration`), which App cannot import across the
 * deptrac boundary. This test cannot observe Core, so it does not detect a Core-side change on its
 * own — the two sides must be kept in sync manually until the endpoints move from Core to App.
 */
final class VaultKeyEnumTest extends TestCase
{
    public function testLiteralValuesMatchCore(): void
    {
        $expected = [
            'HostSnmpCommunity' => '_HOSTSNMPCOMMUNITY',
            'OpenIdClientId' => '_OPENID_CLIENT_ID',
            'OpenIdClientSecret' => '_OPENID_CLIENT_SECRET',
            'DatabaseUsername' => '_DBUSERNAME',
            'DatabasePassword' => '_DBPASSWORD',
            'KnowledgeBasePassword' => '_KBPASSWORD',
            'GorgonePassword' => '_GORGONE_PASSWORD',
        ];

        $actual = [];
        foreach (VaultKeyEnum::cases() as $case) {
            $actual[$case->name] = $case->value;
        }

        self::assertSame($expected, $actual);
    }
}
