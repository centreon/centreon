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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Domain\Model\DashboardRights;

beforeEach(function (): void {
    $this->createContact = function (bool $viewer, bool $creator, bool $admin, bool $superAdmin) {
        $contact = $this->createMock(ContactInterface::class);

        $contact->expects($this->never())->method('isAdmin');

        $contact->expects($this->atLeast($viewer || $creator || $admin ? 1 : 0))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_HOME_DASHBOARD_VIEWER, $viewer],
                    [Contact::ROLE_HOME_DASHBOARD_CREATOR, $creator],
                    [Contact::ROLE_HOME_DASHBOARD_ADMIN, $admin],
                ]
            );

        return $contact;
    };
});

it(
    'should work properly without any rights',
    function (): void {
        $rights = new DashboardRights(
            ($this->createContact)(
                viewer: false,
                creator: false,
                admin: false,
                superAdmin: false,
            )
        );

        expect($rights->canCreate())->toBeFalse()
            ->and($rights->canAccess())->toBeFalse()
            ->and($rights->hasAdminRole())->toBeFalse()
            ->and($rights->hasCreatorRole())->toBeFalse()
            ->and($rights->hasViewerRole())->toBeFalse();
    }
);

it(
    'should work properly with a centreon dashboard VIEWER',
    function (): void {
        $rights = new DashboardRights(
            ($this->createContact)(
                viewer: true,
                creator: false,
                admin: false,
                superAdmin: false,
            )
        );

        expect($rights->canCreate())->toBeFalse()
            ->and($rights->canAccess())->toBeTrue()
            ->and($rights->hasAdminRole())->toBeFalse()
            ->and($rights->hasCreatorRole())->toBeFalse()
            ->and($rights->hasViewerRole())->toBeTrue();
    }
);

it(
    'should work properly with a centreon dashboard CREATOR',
    function (): void {
        $rights = new DashboardRights(
            ($this->createContact)(
                viewer: false,
                creator: true,
                admin: false,
                superAdmin: false,
            )
        );

        expect($rights->canCreate())->toBeTrue()
            ->and($rights->canAccess())->toBeTrue()
            ->and($rights->hasAdminRole())->toBeFalse()
            ->and($rights->hasCreatorRole())->toBeTrue()
            ->and($rights->hasViewerRole())->toBeTrue();
    }
);

it(
    'should work properly with a centreon dashboard ADMIN',
    function (): void {
        $rights = new DashboardRights(
            ($this->createContact)(
                viewer: false,
                creator: false,
                admin: true,
                superAdmin: false,
            )
        );

        expect($rights->canCreate())->toBeTrue()
            ->and($rights->canAccess())->toBeTrue()
            ->and($rights->hasAdminRole())->toBeTrue()
            ->and($rights->hasCreatorRole())->toBeTrue()
            ->and($rights->hasViewerRole())->toBeTrue();
    }
);

it(
    'should work properly with a centreon administrator',
    function (): void {
        $rights = new DashboardRights(
            ($this->createContact)(
                viewer: false,
                creator: false,
                admin: false,
                superAdmin: true,
            )
        );

        expect($rights->canCreate())->toBeFalse()
            ->and($rights->canAccess())->toBeFalse()
            ->and($rights->hasAdminRole())->toBeFalse()
            ->and($rights->hasCreatorRole())->toBeFalse()
            ->and($rights->hasViewerRole())->toBeFalse();
    }
);
