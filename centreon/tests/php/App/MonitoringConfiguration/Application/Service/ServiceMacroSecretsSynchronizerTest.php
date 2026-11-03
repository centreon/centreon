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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

/**
 * The password macros of a service being created: written to a new entry of the vault, shared by them all, under
 * `monitoring/services` and the legacy keys `_SERVICE<NAME>`.
 */
final class ServiceMacroSecretsSynchronizerTest extends TestCase
{
    private const TEMPLATE_SECRET = 'secret::vault::monitoring/services/tpl-uuid::_SERVICETPLPWD';

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
        ]);

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

        $macros = $this->synchronizer->synchronize([$this->macro('mypwd', self::TEMPLATE_SECRET, true)]);

        self::assertSame(['_SERVICEMYPWD' => 'template-secret'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame([['MYPWD', 'secret::vault::monitoring/services/new-uuid::_SERVICEMYPWD', true]], $this->describe($macros));
    }

    public function testAnEmptyPasswordIsStoredAsIs(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', '', true), $this->macro('other', 'x', true)]);

        self::assertSame(['_SERVICEOTHER' => 'x'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame(['PWD', '', true], $this->describe($macros)[0]);
    }

    public function testOnlyEmptyPasswordsWriteNothing(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('pwd', '', true)]);

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([['PWD', '', true]], $this->describe($macros));
    }

    /**
     * A service without a password macro never reaches the vault, not even to ask whether it is enabled.
     */
    public function testItNeverTouchesTheVaultWithoutPasswordMacros(): void
    {
        $macros = $this->synchronizer->synchronize([$this->macro('plain', 'x', false)]);

        self::assertSame([['PLAIN', 'x', false]], $this->describe($macros));
        self::assertSame(0, $this->vault->isEnabledCalls);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testItStoresThePlaintextWhenTheVaultIsDisabled(): void
    {
        $this->vault->vaultEnabled = false;

        $macros = $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)]);

        self::assertSame([['PWD', 's3cr3t', true]], $this->describe($macros));
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAVaultFailureIsLeftToTheCaller(): void
    {
        $this->vault->writeThrows = true;

        $this->expectException(\RuntimeException::class);

        $this->synchronizer->synchronize([$this->macro('pwd', 's3cr3t', true)]);
    }

    private function macro(string $name, string $value, bool $isPassword): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, $isPassword);
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
