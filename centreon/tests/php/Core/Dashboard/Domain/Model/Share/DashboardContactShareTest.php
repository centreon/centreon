<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Dashboard\Domain\Model\Share;

use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Refresh;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

beforeEach(function (): void {
    $this->createDashboardContactShare = function (array $fields = []): DashboardContactShare {
        return new DashboardContactShare(
            new Dashboard(
                99,
                'dashboard-name',
                null,
                null,
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
                new Refresh(RefreshType::Global, null),
            ),
            $fields['id'] ?? 1,
            $fields['name'] ?? 'contact-name',
            $fields['email'] ?? 'contact-email',
            DashboardSharingRoleConverter::fromString($fields['role'] ?? 'viewer')
        );
    };
});

it(
    'should return properly set a dashboard contact share instance',
    function (): void {
        $share = ($this->createDashboardContactShare)();

        expect($share->getDashboard()->getId())->toBe(99)
            ->and($share->getContactId())->toBe(1)
            ->and($share->getContactName())->toBe('contact-name')
            ->and($share->getRole()->name)->toBe(DashboardSharingRole::Viewer->name);
    }
);

// mandatory fields

it(
    'should throw an exception when dashboard contact share name is an empty string',
    fn() => ($this->createDashboardContactShare)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('DashboardContactShare::contactName')->getMessage()
);
it(
    'should throw an exception when dashboard contact share email is an empty string',
    fn() => ($this->createDashboardContactShare)(['email' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('DashboardContactShare::contactEmail')->getMessage()
);

// not positive integers

it(
    'should throw an exception when dashboard contact share id is not a positive integer',
    fn() => ($this->createDashboardContactShare)(['id' => 0])
)->throws(
    AssertionException::class,
    AssertionException::positiveInt(0, 'DashboardContactShare::contactId')->getMessage()
);

