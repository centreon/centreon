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

namespace Tests\Core\HostTemplate\Application;

use Core\HostTemplate\Application\Repository\ReadHostLicenseRepositoryInterface;
use Core\HostTemplate\Application\UnlicensedHostTemplatesFinder;

beforeEach(function (): void {
    $this->readHostLicenseRepository = $this->createMock(ReadHostLicenseRepositoryInterface::class);
    $this->finder = new UnlicensedHostTemplatesFinder($this->readHostLicenseRepository);

    $this->limitReached = function (): void {
        $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10, 11]);
        $this->readHostLicenseRepository->method('isValid')->willReturn(true);
        $this->readHostLicenseRepository->method('getMaxHosts')->willReturn(100);
        $this->readHostLicenseRepository->method('countHosts')->willReturn(100);
    };
});

it('finds nothing and reads nothing when no template is added', function (): void {
    $this->readHostLicenseRepository->expects($this->never())->method('findPaidConnectorTemplateIds');
    $this->readHostLicenseRepository->expects($this->never())->method('isValid');

    expect($this->finder->findAmongAdded([10, 11], [10, 11]))->toBe([]);
});

it('does not read the license when no added template comes from a paid connector', function (): void {
    $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10]);
    $this->readHostLicenseRepository->expects($this->never())->method('isValid');

    expect($this->finder->findAmongAdded([1, 2]))->toBe([]);
});

it('finds the added paid templates once the host limit is reached', function (): void {
    ($this->limitReached)();

    expect($this->finder->findAmongAdded([1, 10]))->toBe([10]);
});

it('keeps the paid templates already given', function (): void {
    ($this->limitReached)();

    expect($this->finder->findAmongAdded([10, 11], [10]))->toBe([11]);
});

it('finds only the paid templates among the added ones', function (): void {
    ($this->limitReached)();

    expect($this->finder->findAmongAdded([1, 10, 2, 11]))->toBe([10, 11]);
});

it('finds nothing while the license allows more hosts than the platform has', function (): void {
    $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10]);
    $this->readHostLicenseRepository->method('isValid')->willReturn(true);
    $this->readHostLicenseRepository->method('getMaxHosts')->willReturn(100);
    $this->readHostLicenseRepository->method('countHosts')->willReturn(99);

    expect($this->finder->findAmongAdded([10]))->toBe([]);
});

it('finds nothing when the license allows any number of hosts', function (): void {
    $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10]);
    $this->readHostLicenseRepository->method('isValid')->willReturn(true);
    $this->readHostLicenseRepository->method('getMaxHosts')->willReturn(null);
    $this->readHostLicenseRepository->expects($this->never())->method('countHosts');

    expect($this->finder->findAmongAdded([10]))->toBe([]);
});

it('considers the limit reached when the platform has more hosts than the license allows', function (): void {
    $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10]);
    $this->readHostLicenseRepository->method('isValid')->willReturn(true);
    $this->readHostLicenseRepository->method('getMaxHosts')->willReturn(100);
    $this->readHostLicenseRepository->method('countHosts')->willReturn(101);

    expect($this->finder->findAmongAdded([10]))->toBe([10]);
});

it('considers the limit reached when the license is not valid', function (): void {
    $this->readHostLicenseRepository->method('findPaidConnectorTemplateIds')->willReturn([10]);
    $this->readHostLicenseRepository->method('isValid')->willReturn(false);
    $this->readHostLicenseRepository->expects($this->never())->method('getMaxHosts');

    expect($this->finder->findAmongAdded([10]))->toBe([10]);
});
