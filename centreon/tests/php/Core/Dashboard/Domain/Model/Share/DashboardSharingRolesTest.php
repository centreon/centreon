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
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

beforeEach(function (): void {
    $this->testedDashboard = new Dashboard(
        1,
        'dashboard-name',

        null,
        null,
        new \DateTimeImmutable(),
        new \DateTimeImmutable(),
        new Refresh(RefreshType::Global, null)
    );
    $this->createContactShare = function (DashboardSharingRole $role) {
        return new DashboardContactShare(
            $this->testedDashboard,
            2,
            'name',
            'email',
            $role
        );
    };
    $this->createContactGroupShares = function (DashboardSharingRole ...$roles) {
        $shares = [];
        foreach ($roles as $index => $role) {
            $shares[] = new DashboardContactGroupShare(
                $this->testedDashboard,
                3 + $index,
                'name',
                $role
            );
        }

        return $shares;
    };
});

it(
    'should return properly set a dashboard sharing roles instance',
    function (): void {
        $sharingRoles = new DashboardSharingRoles(
            $this->testedDashboard,
            $testedContactShare = ($this->createContactShare)(DashboardSharingRole::Viewer),
            $testedContactGroupShare = ($this->createContactGroupShares)(DashboardSharingRole::Viewer),
        );

        expect($sharingRoles->getDashboard())->toBe($this->testedDashboard)
            ->and($sharingRoles->getContactShare())->toBe($testedContactShare)
            ->and($sharingRoles->getContactGroupShares()[0])->toBe($testedContactGroupShare[0]);
    }
);

it(
    'should return the correct most permissive role',
    function (
        ?string $expected,
        ?string $contactRole,
        array $contactGroupRoles
    ): void {
        $toEnum = static fn(?string $string): ?DashboardSharingRole => $string
            ? DashboardSharingRoleConverter::fromString($string)
            : null;

        $sharingRoles = new DashboardSharingRoles(
            $this->testedDashboard,
            $contactRole ? ($this->createContactShare)($toEnum($contactRole)) : null,
            ($this->createContactGroupShares)(...array_map($toEnum, $contactGroupRoles)),
        );

        expect($sharingRoles->getTheMostPermissiveRole()?->name)->toBe($toEnum($expected)?->name);
    }
)->with([
    [null, null, []],
    ['viewer', null, ['viewer']],
    ['viewer', 'viewer', []],
    ['viewer', 'viewer', ['viewer']],
    ['viewer', 'viewer', ['viewer', 'viewer']],
    ['editor', null, ['editor']],
    ['editor', null, ['viewer', 'editor']],
    ['editor', null, ['editor', 'viewer']],
    ['editor', 'viewer', ['editor']],
    ['editor', 'editor', ['viewer', 'viewer']],
]);
