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

use App\MonitoringConfiguration\Application\Service\ServiceMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

/**
 * The password macros of a service: written to its entry of the vault, shared by them all, a new one when it has
 * none yet, under `monitoring/services` and the legacy keys `_SERVICE<NAME>`.
 */
final class ServiceMacroSecretsSynchronizerTest extends TestCase
{
    private const TEMPLATE_SECRET = 'secret::vault::monitoring/services/tpl-uuid::_SERVICETPLPWD';
    private const SERVICE_UUID = 'svc-uuid';
    private const SERVICE_PWD = 'secret::vault::monitoring/services/svc-uuid::_SERVICEPWD';
    private const SERVICE_OLD = 'secret::vault::monitoring/services/svc-uuid::_SERVICEOLD';

    private FakeVault $vault;

    private ServiceMacroSecretsSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->synchronizer = new ServiceMacroSecretsSynchronizer($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testItWritesThePasswordsToOneNewEntryAndKeepsTheirReferences(): void
    {
        $macros = $this->synchronizer->synchronize([
            $this->macro('pwd', 's3cr3t', true),
            $this->macro('port', '8080', false),
            $this->macro('token', 't0k3n', true),
        ], [], null);

        self::assertSame(
            [[
                'customPath' => 'monitoring/services',
                'secrets' => ['_SERVICEPWD' => 's3cr3t', '_SERVICETOKEN' => 't0k3n'],
                'uuid' => null,
                'deletes' => [],
            ]],
            $this->vault->writeManyCalls,
        );
        self::assertSame(
            [
                ['PWD', 'secret::vault::monitoring/services/new-uuid::_SERVICEPWD', true],
                ['PORT', '8080', false],
                ['TOKEN', 'secret::vault::monitoring/services/new-uuid::_SERVICETOKEN', true],
            ],
            $this->describe($macros),
        );
    }

    /**
     * A password promoted from the template keeps the template's reference until here: its secret is read and
     * written again under the service's own entry, never shared through the template's path.
     */
    public function testItCopiesAnInheritedSecretUnderTheNewEntry(): void
    {
        $this->vault->resolved = [self::TEMPLATE_SECRET => 'template-secret'];

        $macros = $this->synchronizer->synchronize([$this->macro('mypwd', self::TEMPLATE_SECRET, true)], [], null);

        self::assertSame(['_SERVICEMYPWD' => 'template-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame([['MYPWD', 'secret::vault::monitoring/services/new-uuid::_SERVICEMYPWD', true]], $this->describe($macros));
    }

    public function testAnEmptyPasswordIsStoredAsIs(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', '', true), $this->macro('other', 'x', true)], [], null);

        self::assertSame(['_SERVICEOTHER' => 'x'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame(['PWD', '', true], $this->describe($macros)[0]);
    }

    public function testOnlyEmptyPasswordsWriteNothing(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', '', true)], [], null);

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([['PWD', '', true]], $this->describe($macros));
    }

    /**
     * A service without a password macro never reaches the vault, not even to ask whether it is enabled.
     */
    public function testItNeverTouchesTheVaultWithoutPasswordMacros(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('plain', 'x', false)], [], null);

        self::assertSame([['PLAIN', 'x', false]], $this->describe($macros));
        self::assertSame(0, $this->vault->isEnabledCalls);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItStoresThePlaintextWhenTheVaultIsDisabled(): void
    {
        $this->vault->vaultEnabled = false;

        $macros = $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], null);

        self::assertSame([['PWD', 's3cr3t', true]], $this->describe($macros));
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAVaultFailureIsLeftToTheCaller(): void
    {
        $this->vault->writeThrows = true;

        $this->expectException(\RuntimeException::class);

        $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], null);
    }

    public function testItWritesUnderTheEntryTheServiceAlreadyHas(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)], [], self::SERVICE_UUID);

        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => ['_SERVICEPWD' => 's3cr3t'], 'uuid' => self::SERVICE_UUID, 'deletes' => []]],
            $this->vault->writeManyCalls,
        );
        self::assertSame([['PWD', 'secret::vault::monitoring/services/svc-uuid::_SERVICEPWD', true]], $this->describe($macros));
    }

    /**
     * A kept or renamed password keeps its reference: nothing is written.
     */
    public function testItLeavesAReferenceAlreadyInTheServiceEntryUntouched(): void
    {
        $macros = $this->synchronizer->synchronize(
            [$this->macro('renamed', self::SERVICE_PWD, true, 5)],
            [$this->macro('pwd', self::SERVICE_PWD, true, 5)],
            self::SERVICE_UUID,
        );

        self::assertSame([['RENAMED', self::SERVICE_PWD, true]], $this->describe($macros));
        self::assertSame([], $this->vault->writeManyCalls);
    }

    /**
     * Removed, collapsed to inherited, or turned into a plain macro: its key leaves the entry.
     */
    public function testItDeletesTheKeyOfAPasswordNoLongerOwned(): void
    {
        $macros = $this->synchronizer->synchronize(
            [$this->macro('pwd', 'now-plain', false, 5)],
            [$this->macro('pwd', self::SERVICE_PWD, true, 5)],
            self::SERVICE_UUID,
        );

        self::assertSame([['PWD', 'now-plain', false]], $this->describe($macros));
        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => [], 'uuid' => self::SERVICE_UUID, 'deletes' => ['_SERVICEPWD']]],
            $this->vault->writeManyCalls,
        );
    }

    public function testANewValueOverwritesTheSameKeyWithoutDeletingIt(): void
    {
        $this->synchronizer->synchronize(
            [$this->macro('pwd', 'new-secret', true, 5)],
            [$this->macro('pwd', self::SERVICE_PWD, true, 5)],
            self::SERVICE_UUID,
        );

        self::assertSame(
            [['customPath' => 'monitoring/services', 'secrets' => ['_SERVICEPWD' => 'new-secret'], 'uuid' => self::SERVICE_UUID, 'deletes' => []]],
            $this->vault->writeManyCalls,
        );
    }

    /**
     * OLD was renamed NEW (it still points to _SERVICEOLD) while a new OLD claims that key: the renamed macro
     * moves under its own name instead of losing its secret.
     */
    public function testARenamedPasswordWhoseKeyIsClaimedMovesUnderItsOwnName(): void
    {
        $this->vault->resolved = [self::SERVICE_OLD => 'renamed-secret'];

        $macros = $this->synchronizer->synchronize(
            [$this->macro('new', self::SERVICE_OLD, true, 5), $this->macro('old', 'fresh-secret', true)],
            [$this->macro('old', self::SERVICE_OLD, true, 5)],
            self::SERVICE_UUID,
        );

        self::assertSame(['_SERVICEOLD' => 'fresh-secret', '_SERVICENEW' => 'renamed-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame([], $this->vault->writeManyCalls[0]['deletes']);
        self::assertSame(
            [
                ['NEW', 'secret::vault::monitoring/services/svc-uuid::_SERVICENEW', true],
                ['OLD', 'secret::vault::monitoring/services/svc-uuid::_SERVICEOLD', true],
            ],
            $this->describe($macros),
        );
    }

    /**
     * A was renamed C (it still points to _SERVICEA) and B renamed A (it still points to _SERVICEB) while a new B
     * claims _SERVICEB: moving A under _SERVICEA claims the key C points to, so C moves too.
     */
    public function testChainedRenamesEachKeepTheirOwnSecret(): void
    {
        $refA = 'secret::vault::monitoring/services/svc-uuid::_SERVICEA';
        $refB = 'secret::vault::monitoring/services/svc-uuid::_SERVICEB';
        $this->vault->resolved = [$refA => 'secret-of-a', $refB => 'secret-of-b'];

        $this->synchronizer->synchronize(
            [$this->macro('c', $refA, true, 1), $this->macro('a', $refB, true, 2), $this->macro('b', 'fresh-secret', true)],
            [$this->macro('a', $refA, true, 1), $this->macro('b', $refB, true, 2)],
            self::SERVICE_UUID,
        );

        $written = $this->vault->writeManyCalls[0]['secrets'];
        ksort($written);
        self::assertSame(['_SERVICEA' => 'secret-of-b', '_SERVICEB' => 'fresh-secret', '_SERVICEC' => 'secret-of-a'], $written);
    }

    /**
     * The last password removed still reaches the vault, to delete its key.
     */
    public function testRemovingTheLastPasswordDeletesItsKey(): void
    {
        $this->synchronizer->synchronize([], [$this->macro('pwd', self::SERVICE_PWD, true, 5)], self::SERVICE_UUID);

        self::assertSame(['_SERVICEPWD'], $this->vault->writeManyCalls[0]['deletes']);
    }

    private function macro(string $name, string $value, bool $isPassword, ?int $id = null): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, $isPassword, id: $id !== null ? new ServiceMacroId($id) : null);
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return list<array{string, string, bool}>
     */
    private function describe(array $macros): array
    {
        return array_map(static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->isPassword], $macros);
    }
}
