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

namespace Tests\App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Credential;
use App\Security\Infrastructure\Dbal\DbalCredentialRepository;
use App\Security\Infrastructure\Dbal\DbalCredentialTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Whether the contact actually is a Cloud admin is resolved by {@see DbalCredentialRepository}
 * and reaches the transformer as the "is_cloud_admin" row key — the on-premise / Cloud
 * branching itself is covered by DbalCredentialRepositoryTest.
 *
 * @phpstan-import-type RowTypeAlias from DbalCredentialRepository
 */
final class DbalCredentialTransformerTest extends TestCase
{
    #[DataProvider('adminshipProvider')]
    public function testAdminshipIsMappedToRoles(
        bool $contactAdmin,
        bool $isCloudAdmin,
        bool $expectedSuperAdmin,
        bool $expectedCloudAdmin,
    ): void {
        $credential = $this->transform($contactAdmin, $isCloudAdmin);

        self::assertSame($expectedSuperAdmin, $credential->isSuperAdmin());
        self::assertSame($expectedCloudAdmin, $credential->isCloudAdmin());
        self::assertSame($expectedSuperAdmin || $expectedCloudAdmin, $credential->hasUnrestrictedResourceAccess());
    }

    /**
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function adminshipProvider(): iterable
    {
        yield 'plain contact' => [false, false, false, false];

        yield 'super admin' => [true, false, true, false];

        yield 'cloud admin' => [false, true, false, true];

        yield 'both' => [true, true, true, true];
    }

    public function testIdentityIsMapped(): void
    {
        $credential = $this->transform(contactAdmin: false, isCloudAdmin: false);

        self::assertSame('jdoe', $credential->identifier->value);
        self::assertSame(42, $credential->userId->value);
        self::assertTrue($credential->active);
    }

    private function transform(bool $contactAdmin, bool $isCloudAdmin): Credential
    {
        /** @var RowTypeAlias $row */
        $row = [
            'c_id' => 42,
            'c_alias' => 'jdoe',
            'c_admin' => $contactAdmin ? '1' : '0',
            'c_active' => '1',
            'is_cloud_admin' => $isCloudAdmin,
            'topology_permissions' => [],
            'action_rules' => [],
        ];

        return (new DbalCredentialTransformer())->transform($row);
    }
}
