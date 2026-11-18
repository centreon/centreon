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

use App\MonitoringConfiguration\Application\Service\HostLicenseLimit;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Shared\Domain\License\HostLicenseLimitInterface;
use App\Shared\Domain\License\LicenseCheckInterface;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeModuleRepository;

final class HostLicenseLimitTest extends TestCase
{
    private const LICENSE_MANAGER = 'centreon-license-manager';

    private HostRepository $hostRepository;

    protected function setUp(): void
    {
        // The platform has two hosts.
        $hostRepository = $this->createStub(HostRepository::class);
        $hostRepository->method('countAll')->willReturn(2);
        $this->hostRepository = $hostRepository;
    }

    public function testItIsNotReachedWhileTheLicenseAllowsMoreHosts(): void
    {
        self::assertFalse($this->limit(maxHosts: 3)->isReached());
    }

    public function testItIsNotReachedWhenTheLicenseAllowsAnyNumberOfHosts(): void
    {
        self::assertFalse($this->limit(maxHosts: null)->isReached());
    }

    public function testItIsReachedOnceThePlatformHasAsManyHostsAsTheLicenseAllows(): void
    {
        self::assertTrue($this->limit(maxHosts: 2)->isReached());
    }

    public function testItIsReachedWhenTheLicenseIsInvalid(): void
    {
        self::assertTrue($this->limit(maxHosts: null, valid: false)->isReached());
    }

    public function testItIsReachedWhenTheLicenseManagerIsNotInstalled(): void
    {
        self::assertTrue($this->limit(maxHosts: null, installed: [])->isReached());
    }

    public function testItIsReachedWithoutTheLicenseServices(): void
    {
        $limit = new HostLicenseLimit(new FakeModuleRepository([self::LICENSE_MANAGER]), $this->hostRepository);

        self::assertTrue($limit->isReached());
    }

    /**
     * @param list<string> $installed
     */
    private function limit(?int $maxHosts, bool $valid = true, array $installed = [self::LICENSE_MANAGER]): HostLicenseLimit
    {
        $licenseCheck = new class ($valid) implements LicenseCheckInterface {
            public function __construct(private readonly bool $valid)
            {
            }

            public function isValid(string $product, bool $hostCheck = true): bool
            {
                return $this->valid && $product === HostLicenseLimit::LICENSE_PRODUCT;
            }
        };
        $hostLicenseLimit = new class ($maxHosts) implements HostLicenseLimitInterface {
            public function __construct(private readonly ?int $maxHosts)
            {
            }

            public function getMaxHosts(string $product): ?int
            {
                return $this->maxHosts;
            }
        };

        return new HostLicenseLimit(new FakeModuleRepository($installed), $this->hostRepository, $licenseCheck, $hostLicenseLimit);
    }
}
