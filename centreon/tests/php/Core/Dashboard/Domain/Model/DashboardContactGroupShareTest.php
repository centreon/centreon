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

namespace Tests\Core\Dashboard\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\DashboardSharingRole;

beforeEach(function (): void {
    $this->createDashboardContactGroupShare = function (array $fields = []): DashboardContactGroupShare {
        return new DashboardContactGroupShare(
            new Dashboard(
                99,
                'dashboard-name',
                'dashboard-description',
                null,
                null,
                new \DateTimeImmutable(),
                new \DateTimeImmutable()
            ),
            $fields['id'] ?? 1,
            $fields['name'] ?? 'contact-group-name',
            DashboardSharingRole::tryFrom($fields['role'] ?? '') ?? DashboardSharingRole::Viewer
        );
    };
});

it(
    'should return properly set a dashboard contact group share instance',
    function (): void {
        $share = ($this->createDashboardContactGroupShare)();

        expect($share->getDashboard()->getId())->toBe(99)
            ->and($share->getContactGroupId())->toBe(1)
            ->and($share->getContactGroupName())->toBe('contact-group-name')
            ->and($share->getRole())->toBe(DashboardSharingRole::Viewer);
    }
);

// mandatory fields

it(
    'should throw an exception when dashboard contact group share name is an empty string',
    fn() => ($this->createDashboardContactGroupShare)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('DashboardContactGroupShare::contactGroupName')->getMessage()
);

// not positive integers

it(
    'should throw an exception when dashboard contact group share id is not a positive integer',
    fn() => ($this->createDashboardContactGroupShare)(['id' => 0])
)->throws(
    AssertionException::class,
    AssertionException::positiveInt(0, 'DashboardContactGroupShare::contactGroupId')->getMessage()
);

