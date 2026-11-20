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

namespace Tests\App\Shared\Application\Vault;

use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class VaultCredentialWriterTest extends TestCase
{
    public function testDoesNotTouchVaultWhenNothingToPersist(): void
    {
        $vault = new FakeVault();
        $writer = new VaultCredentialWriter($vault);

        $credentials = VaultCredentials::fromArray([
            'empty' => '',
            'already' => 'secret::vault::monitoring/hosts/uuid::already',
        ]);

        $result = $writer->write(VaultPathEnum::MonitoringHosts, $credentials);

        self::assertSame($credentials->toArray(), $result);
        self::assertSame([], $vault->writeManyCalls);
    }

    public function testSendsOnlyPlaintextAndMergesReturnedPaths(): void
    {
        $vault = new FakeVault();
        $vault->writtenPaths = [
            '_HOSTSNMPCOMMUNITY' => 'secret::vault::monitoring/hosts/new-uuid::_HOSTSNMPCOMMUNITY',
        ];
        $writer = new VaultCredentialWriter($vault);

        $credentials = VaultCredentials::fromArray([
            '_HOSTSNMPCOMMUNITY' => 'public',
            'empty' => '',
            'already' => 'secret::vault::monitoring/hosts/uuid::already',
        ]);

        $result = $writer->write(VaultPathEnum::MonitoringHosts, $credentials);

        // Vaulted key replaced with its path; empty + already-vaulted values passed through untouched.
        self::assertSame([
            '_HOSTSNMPCOMMUNITY' => 'secret::vault::monitoring/hosts/new-uuid::_HOSTSNMPCOMMUNITY',
            'empty' => '',
            'already' => 'secret::vault::monitoring/hosts/uuid::already',
        ], $result);

        // Only the plaintext credential was sent to the vault, under the resource path, fresh UUID.
        self::assertCount(1, $vault->writeManyCalls);
        self::assertSame('monitoring/hosts', $vault->writeManyCalls[0]['customPath']);
        self::assertSame(['_HOSTSNMPCOMMUNITY' => 'public'], $vault->writeManyCalls[0]['secrets']);
        self::assertNull($vault->writeManyCalls[0]['uuid']);
    }

    public function testForwardsProvidedUuid(): void
    {
        $vault = new FakeVault();
        $writer = new VaultCredentialWriter($vault);

        $writer->write(
            VaultPathEnum::MonitoringHosts,
            VaultCredentials::fromArray(['MACRO' => 'value']),
            'existing-uuid',
        );

        self::assertSame('existing-uuid', $vault->writeManyCalls[0]['uuid']);
    }

    public function testWriteFailurePropagates(): void
    {
        $vault = new FakeVault();
        $vault->writeThrows = true;

        $writer = new VaultCredentialWriter($vault);

        $this->expectException(\RuntimeException::class);

        $writer->write(VaultPathEnum::MonitoringHosts, VaultCredentials::fromArray(['MACRO' => 'value']));
    }

    public function testPersistWritesSetDeletesClearedAndKeepsTheRest(): void
    {
        $vault = new FakeVault();
        $vault->writtenPaths = [
            'MACRO_A' => 'secret::vault::monitoring/hosts/existing-uuid::MACRO_A',
        ];
        $writer = new VaultCredentialWriter($vault);

        $credentials = VaultCredentials::empty()
            ->set('MACRO_A', 'newpass')
            ->clear('MACRO_B')
            ->keep('MACRO_C', 'secret::vault::monitoring/hosts/existing-uuid::MACRO_C');

        $result = $writer->persist(VaultPathEnum::MonitoringHosts, $credentials, 'existing-uuid');

        // Set -> fresh path; Cleared -> dropped from the map; Unchanged -> carried through untouched.
        self::assertSame([
            'MACRO_A' => 'secret::vault::monitoring/hosts/existing-uuid::MACRO_A',
            'MACRO_C' => 'secret::vault::monitoring/hosts/existing-uuid::MACRO_C',
        ], $result);

        // Only the set credential is inserted; the cleared key is deleted; all under the same entry.
        self::assertCount(1, $vault->writeManyCalls);
        self::assertSame('monitoring/hosts', $vault->writeManyCalls[0]['customPath']);
        self::assertSame(['MACRO_A' => 'newpass'], $vault->writeManyCalls[0]['secrets']);
        self::assertSame('existing-uuid', $vault->writeManyCalls[0]['uuid']);
        self::assertSame(['MACRO_B'], $vault->writeManyCalls[0]['deletes']);
    }

    public function testPersistDeletesOnlyWhenNothingIsSet(): void
    {
        $vault = new FakeVault();
        $writer = new VaultCredentialWriter($vault);

        $credentials = VaultCredentials::empty()
            ->clear('MACRO_B')
            ->keep('MACRO_C', 'secret::vault::monitoring/hosts/existing-uuid::MACRO_C');

        $result = $writer->persist(VaultPathEnum::MonitoringHosts, $credentials, 'existing-uuid');

        // Cleared key dropped from the returned map; only the unchanged one is carried through.
        self::assertSame([
            'MACRO_C' => 'secret::vault::monitoring/hosts/existing-uuid::MACRO_C',
        ], $result);

        self::assertCount(1, $vault->writeManyCalls);
        self::assertSame([], $vault->writeManyCalls[0]['secrets']);
        self::assertSame(['MACRO_B'], $vault->writeManyCalls[0]['deletes']);
    }

    public function testPersistDoesNotTouchVaultWhenNothingSetOrCleared(): void
    {
        $vault = new FakeVault();
        $writer = new VaultCredentialWriter($vault);

        $credentials = VaultCredentials::empty()
            ->keep('MACRO_C', 'secret::vault::monitoring/hosts/existing-uuid::MACRO_C');

        $result = $writer->persist(VaultPathEnum::MonitoringHosts, $credentials, 'existing-uuid');

        self::assertSame($credentials->toArray(), $result);
        self::assertSame([], $vault->writeManyCalls);
    }

    public function testPersistFailurePropagates(): void
    {
        $vault = new FakeVault();
        $vault->writeThrows = true;

        $writer = new VaultCredentialWriter($vault);

        $this->expectException(\RuntimeException::class);

        $writer->persist(VaultPathEnum::MonitoringHosts, VaultCredentials::empty()->set('MACRO', 'value'));
    }

    public function testDeleteForwardsPathAndUuidToVault(): void
    {
        $vault = new FakeVault();
        $writer = new VaultCredentialWriter($vault);

        $writer->delete(VaultPathEnum::MonitoringHosts, 'entry-uuid');

        self::assertSame([
            ['customPath' => 'monitoring/hosts', 'uuid' => 'entry-uuid'],
        ], $vault->deleteCalls);
    }

    public function testDeleteFailurePropagates(): void
    {
        $vault = new FakeVault();
        $vault->deleteThrows = true;

        $writer = new VaultCredentialWriter($vault);

        $this->expectException(\RuntimeException::class);

        $writer->delete(VaultPathEnum::MonitoringHosts, 'entry-uuid');
    }
}
