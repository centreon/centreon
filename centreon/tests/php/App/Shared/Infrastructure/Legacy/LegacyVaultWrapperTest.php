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

namespace Tests\App\Shared\Infrastructure\Legacy;

use App\Shared\Infrastructure\Legacy\LegacyContainer;
use App\Shared\Infrastructure\Legacy\LegacyVaultWrapper;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\VaultEligibilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LegacyVaultWrapperTest extends TestCase
{
    private ReadVaultRepositoryInterface&MockObject $readRepository;

    private WriteVaultRepositoryInterface&MockObject $writeRepository;

    private VaultEligibilityService&MockObject $eligibilityService;

    private LegacyVaultWrapper $wrapper;

    protected function setUp(): void
    {
        $this->readRepository = $this->createMock(ReadVaultRepositoryInterface::class);
        $this->writeRepository = $this->createMock(WriteVaultRepositoryInterface::class);
        $this->eligibilityService = $this->createMock(VaultEligibilityService::class);

        $container = $this->createMock(LegacyContainer::class);
        $container->method('get')->willReturnCallback(fn (string $id): object => match ($id) {
            ReadVaultRepositoryInterface::class => $this->readRepository,
            WriteVaultRepositoryInterface::class => $this->writeRepository,
            VaultEligibilityService::class => $this->eligibilityService,
            default => throw new \InvalidArgumentException(sprintf('Unexpected service "%s"', $id)),
        });

        $this->wrapper = new LegacyVaultWrapper($container);
    }

    public function testItResolvesNothingUntilALegacyServiceIsActuallyNeeded(): void
    {
        $container = $this->createMock(LegacyContainer::class);
        $container->expects(self::never())->method('get');

        $wrapper = new LegacyVaultWrapper($container);

        self::assertTrue($wrapper->isVaultPath('secret::path'));
        self::assertFalse($wrapper->isVaultPath('plaintext'));
    }

    public function testResolvingAPlaintextValueTouchesNoLegacyService(): void
    {
        $container = $this->createMock(LegacyContainer::class);
        $container->expects(self::never())->method('get');

        self::assertSame('public', new LegacyVaultWrapper($container)->resolve('public'));
    }

    public function testItResolvesAVaultPathToItsSecret(): void
    {
        $path = 'secret::hashicorp_vault::monitoring/hosts/3f2a::_HOSTSNMPCOMMUNITY';
        $this->readRepository->expects(self::once())
            ->method('findFromPath')
            ->with($path)
            ->willReturn(['_HOSTSNMPCOMMUNITY' => 'public']);

        self::assertSame('public', $this->wrapper->resolve($path));
    }

    public function testItFailsWhenTheVaultHoldsNoSecretUnderTheKey(): void
    {
        $this->readRepository->method('findFromPath')->willReturn([]);

        $this->expectException(\RuntimeException::class);

        $this->wrapper->resolve('secret::hashicorp_vault::monitoring/hosts/3f2a::_HOSTSNMPCOMMUNITY');
    }

    public function testWriteManyReturnsOnlyRequestedKeysWhenEntryHoldsOthers(): void
    {
        $this->writeRepository->expects($this->once())->method('setCustomPath')->with('monitoring/hosts');
        // The underlying repository writes to an existing entry and returns a path for every key
        // stored under the UUID, including pre-existing ones not part of this request.
        $this->writeRepository->method('upsert')->with('existing-uuid', ['_HOSTKEY' => 'value'], [])->willReturn([
            '_HOSTSNMPCOMMUNITY' => 'secret::vault::monitoring/hosts/existing-uuid::_HOSTSNMPCOMMUNITY',
            '_HOSTKEY' => 'secret::vault::monitoring/hosts/existing-uuid::_HOSTKEY',
        ]);

        $result = $this->wrapper->writeMany('monitoring/hosts', ['_HOSTKEY' => 'value'], 'existing-uuid');

        // Only the requested key is returned; the surplus path never leaks onto the caller.
        self::assertSame([
            '_HOSTKEY' => 'secret::vault::monitoring/hosts/existing-uuid::_HOSTKEY',
        ], $result);
    }

    public function testWriteDelegatesToWriteManyAndReturnsSinglePath(): void
    {
        $this->writeRepository->method('upsert')->with(null, ['_HOSTKEY' => 'value'], [])->willReturn([
            '_HOSTKEY' => 'secret::vault::monitoring/hosts/new-uuid::_HOSTKEY',
        ]);

        $path = $this->wrapper->write('monitoring/hosts', '_HOSTKEY', 'value');

        self::assertSame('secret::vault::monitoring/hosts/new-uuid::_HOSTKEY', $path);
    }

    public function testWriteManyThrowsWhenRequestedKeyMissingFromResult(): void
    {
        $this->writeRepository->method('upsert')->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write vault credential "_HOSTKEY"');

        $this->wrapper->writeMany('monitoring/hosts', ['_HOSTKEY' => 'value']);
    }
}
