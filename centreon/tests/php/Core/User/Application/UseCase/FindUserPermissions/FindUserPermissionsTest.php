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

namespace Tests\Core\User\Application\UseCase\FindUserPermissions;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\User\Application\UseCase\FindUserPermissions\FindUserPermissions;
use Core\User\Application\UseCase\FindUserPermissions\FindUserPermissionsResponse;
use Core\User\Domain\Model\Permission;

beforeEach(function (): void {
    $this->useCase = new FindUserPermissions();
    $this->user = $this->createMock(ContactInterface::class);
    $this->getPermissionNames = static fn (FindUserPermissionsResponse $response): array => array_map(
        static fn (Permission $permission): string => (string) $permission->getName(),
        $response->permissions
    );
});

it('should return every permission as active when the user is an admin', function (): void {
    $this->user->method('isAdmin')->willReturn(true);

    $response = ($this->useCase)($this->user);

    expect($response)->toBeInstanceOf(FindUserPermissionsResponse::class)
        ->and(($this->getPermissionNames)($response))->toBe([
            'top_counter',
            'poller_statistics',
            'configuration_host_group_write',
            'configuration_host_write',
            'see_check_commands',
            'manage_check_commands',
            'see_notification_commands',
            'manage_notification_commands',
            'see_discovery_commands',
            'manage_discovery_commands',
            'see_miscellaneous_commands',
            'manage_miscellaneous_commands',
            'create_edit_poller_cfg',
        ]);

    foreach ($response->permissions as $permission) {
        expect($permission->isActive())->toBeTrue();
    }
});

it('should return no permission when the user is not an admin and has no role', function (): void {
    $this->user->method('isAdmin')->willReturn(false);
    $this->user->method('hasRole')->willReturn(false);
    $this->user->method('hasTopologyRole')->willReturn(false);

    $response = ($this->useCase)($this->user);

    expect($response)->toBeInstanceOf(FindUserPermissionsResponse::class)
        ->and($response->permissions)->toBe([]);
});

it(
    'should return only the matching permission when the user has the corresponding role',
    function (string $role, string $expectedPermission): void {
        $this->user->method('isAdmin')->willReturn(false);
        $this->user->method('hasRole')->willReturnCallback(static fn (string $userRole): bool => $userRole === $role);
        $this->user->method('hasTopologyRole')->willReturn(false);

        $response = ($this->useCase)($this->user);

        expect(($this->getPermissionNames)($response))->toBe([$expectedPermission])
            ->and($response->permissions[0]->isActive())->toBeTrue();
    }
)->with([
    'top counter' => [Contact::ROLE_DISPLAY_TOP_COUNTER, 'top_counter'],
    'poller statistics' => [Contact::ROLE_DISPLAY_TOP_COUNTER_POLLERS_STATISTICS, 'poller_statistics'],
    'host group write' => [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE, 'configuration_host_group_write'],
    'host write' => [Contact::ROLE_CONFIGURATION_HOSTS_WRITE, 'configuration_host_write'],
    'see check commands' => [Contact::ROLE_SEE_CHECK_COMMANDS, 'see_check_commands'],
    'manage check commands' => [Contact::ROLE_MANAGE_CHECK_COMMANDS, 'manage_check_commands'],
    'see notification commands' => [Contact::ROLE_SEE_NOTIFICATION_COMMANDS, 'see_notification_commands'],
    'manage notification commands' => [Contact::ROLE_MANAGE_NOTIFICATION_COMMANDS, 'manage_notification_commands'],
    'see discovery commands' => [Contact::ROLE_SEE_DISCOVERY_COMMANDS, 'see_discovery_commands'],
    'manage discovery commands' => [Contact::ROLE_MANAGE_DISCOVERY_COMMANDS, 'manage_discovery_commands'],
    'see miscellaneous commands' => [Contact::ROLE_SEE_MISCELLANEOUS_COMMANDS, 'see_miscellaneous_commands'],
    'manage miscellaneous commands' => [Contact::ROLE_MANAGE_MISCELLANEOUS_COMMANDS, 'manage_miscellaneous_commands'],
    'create and edit pollers' => [Contact::ROLE_CREATE_EDIT_POLLER_CFG, 'create_edit_poller_cfg'],
]);

it('should return the permission when the user has the corresponding topology role', function (): void {
    $this->user->method('isAdmin')->willReturn(false);
    $this->user->method('hasRole')->willReturn(false);
    $this->user->method('hasTopologyRole')->willReturnCallback(
        static fn (string $role): bool => $role === Contact::ROLE_CONFIGURATION_HOSTS_WRITE
    );

    $response = ($this->useCase)($this->user);

    expect(($this->getPermissionNames)($response))->toBe(['configuration_host_write']);
});
