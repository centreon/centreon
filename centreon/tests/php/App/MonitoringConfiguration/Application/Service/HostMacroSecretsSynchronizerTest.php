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

namespace Tests\App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Service\HostMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeVault;

final class HostMacroSecretsSynchronizerTest extends TestCase
{
    private const HOST_UUID = 'host-uuid';
    private const HOST_PWD = 'secret::vault::monitoring/hosts/host-uuid::_HOSTPWD';
    private const HOST_OLD = 'secret::vault::monitoring/hosts/host-uuid::_HOSTOLD';
    private const TEMPLATE_PWD = 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTTPLPWD';

    private FakeVault $vault;

    private HostMacroSecretsSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->vault->extractedUuids = [
            self::HOST_PWD => self::HOST_UUID,
            self::HOST_OLD => self::HOST_UUID,
            self::TEMPLATE_PWD => 'tpl-uuid',
        ];
        $this->synchronizer = new HostMacroSecretsSynchronizer($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testItWritesAPlaintextPasswordUnderTheHostEntry(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], self::HOST_UUID);

        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTPWD', $macros[0]->value);
        self::assertSame([['customPath' => 'monitoring/hosts', 'key' => '_HOSTPWD', 'value' => 's3cr3t', 'uuid' => self::HOST_UUID]], $this->vault->writeCalls);
    }

    public function testItLeavesAReferenceAlreadyInTheHostEntryUntouched(): void
    {
        // A kept or renamed direct password keeps its reference.
        $macros = $this->synchronizer->synchronize(
            [$this->macro('renamed', self::HOST_PWD, true, 5)],
            [$this->macro('pwd', self::HOST_PWD, true, 5)],
            self::HOST_UUID,
        );

        self::assertSame(self::HOST_PWD, $macros[0]->value);
        self::assertSame([], $this->vault->writeCalls);
        self::assertSame([], $this->vault->deletedKeys);
    }

    public function testItCopiesAnInheritedSecretUnderTheHostEntry(): void
    {
        // Never the template's path.
        $this->vault->resolved = [self::TEMPLATE_PWD => 'template-secret'];

        $macros = $this->synchronizer->synchronize([$this->macro('mypwd', self::TEMPLATE_PWD, true)], [], self::HOST_UUID);

        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTMYPWD', $macros[0]->value);
        self::assertSame('template-secret', $this->vault->writeCalls[0]['value']);
        self::assertSame(self::HOST_UUID, $this->vault->writeCalls[0]['uuid']);
    }

    public function testItMintsAnEntryWhenTheHostHasNone(): void
    {
        $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], null);

        self::assertNull($this->vault->writeCalls[0]['uuid']);
    }

    public function testAnEmptyPasswordIsNotVaulted(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', '', true)], [], self::HOST_UUID);

        self::assertSame('', $macros[0]->value);
        self::assertSame([], $this->vault->writeCalls);
    }

    public function testItDeletesTheKeyOfAPasswordNoLongerOwned(): void
    {
        // Removed, collapsed to inherited, or turned into a plain macro.
        $macros = $this->synchronizer->synchronize(
            [$this->macro('pwd', 'now-plain', false, 5)],
            [$this->macro('pwd', self::HOST_PWD, true, 5)],
            self::HOST_UUID,
        );

        self::assertSame('now-plain', $macros[0]->value);
        self::assertSame([['uuid' => self::HOST_UUID, 'deletes' => ['_HOSTPWD']]], $this->vault->deletedKeys);
    }

    public function testANewValueOverwritesTheSameKeyWithoutDeletingIt(): void
    {
        $this->synchronizer->synchronize(
            [$this->macro('pwd', 'new-secret', true, 5)],
            [$this->macro('pwd', self::HOST_PWD, true, 5)],
            self::HOST_UUID,
        );

        self::assertSame('_HOSTPWD', $this->vault->writeCalls[0]['key']);
        self::assertSame([], $this->vault->deletedKeys);
    }

    public function testARenamedPasswordWhoseKeyIsClaimedMovesUnderItsOwnName(): void
    {
        // OLD was renamed to NEW (keeps ..::_HOSTOLD) while a new OLD claims the _HOSTOLD key.
        $this->vault->resolved = [self::HOST_OLD => 'renamed-secret'];

        $macros = $this->synchronizer->synchronize(
            [$this->macro('new', self::HOST_OLD, true, 5), $this->macro('old', 'fresh-secret', true)],
            [$this->macro('old', self::HOST_OLD, true, 5)],
            self::HOST_UUID,
        );

        $written = array_column($this->vault->writeCalls, 'value', 'key');
        self::assertSame(['_HOSTOLD' => 'fresh-secret', '_HOSTNEW' => 'renamed-secret'], $written);
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTNEW', $macros[0]->value);
        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTOLD', $macros[1]->value);
        self::assertSame([], $this->vault->deletedKeys);
    }

    public function testALaterPasswordSharingTheNameOfAPlainMacroIsNotVaulted(): void
    {
        // The first macro of a name wins whatever its type: vaulting the later one would leave a
        // secret no stored macro references.
        $this->synchronizer->synchronize(
            [$this->macro('pwd', 'plain', false), $this->macro('PWD', 's3cr3t', true)],
            [],
            self::HOST_UUID,
        );

        self::assertSame([], $this->vault->writeCalls);
    }

    public function testALaterDuplicateOfARenamedPasswordIsNotMoved(): void
    {
        $this->vault->resolved = [self::HOST_OLD => 'renamed-secret'];

        $this->synchronizer->synchronize(
            // NEW is a plain macro: the later password NEW, renamed from OLD, must not be moved to _HOSTNEW.
            [$this->macro('new', 'plain', false), $this->macro('old', 'fresh-secret', true), $this->macro('NEW', self::HOST_OLD, true, 5)],
            [$this->macro('old', self::HOST_OLD, true, 5)],
            self::HOST_UUID,
        );

        self::assertSame(['_HOSTOLD' => 'fresh-secret'], array_column($this->vault->writeCalls, 'value', 'key'));
    }

    public function testItNeverTouchesTheVaultWithoutPasswordMacros(): void
    {
        $this->vault->vaultEnabled = true;

        $macros = $this->synchronizer->synchronize([$this->macro('plain', 'x', false)], [], self::HOST_UUID);

        self::assertSame('x', $macros[0]->value);
        self::assertSame([], $this->vault->writeCalls);
    }

    public function testItStoresPlaintextWhenTheVaultIsDisabled(): void
    {
        $this->vault->vaultEnabled = false;

        $macros = $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], null);

        self::assertSame('s3cr3t', $macros[0]->value);
        self::assertSame([], $this->vault->writeCalls);
    }

    private function macro(string $name, string $value, bool $isPassword, ?int $id = null): HostMacro
    {
        return new HostMacro(new HostMacroName($name), $value, $isPassword, $id !== null ? new HostMacroId($id) : null);
    }
}
