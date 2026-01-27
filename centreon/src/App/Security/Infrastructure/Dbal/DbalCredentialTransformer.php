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

namespace App\Security\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Security\GlobalMacroPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\ServiceCategoryPermissionEnum;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalCredentialRepository
 *
 * @implements TransformerInterface<RowTypeAlias, Credential>
 */
final readonly class DbalCredentialTransformer implements TransformerInterface
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_PERMISSION_MAP = [
        'ROLE_CONFIGURATION_SERVICES_CATEGORIES_R' => ServiceCategoryPermissionEnum::CanRead->value,
        'ROLE_CONFIGURATION_SERVICES_CATEGORIES_RW' => ServiceCategoryPermissionEnum::CanWrite->value,
        'ROLE_CONFIGURATION_POLLERS_RESOURCES_RW' => GlobalMacroPermissionEnum::CanRead->value,
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_ROLE_MAP = [
    ];

    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): Credential
    {
        $credential = new Credential(
            identifier: new CredentialIdentifier($from['c_alias']),
            userId: new UserId($from['c_id']),
            active: $from['c_active'] === '1',
        );

        foreach ($from['topology_permissions'] as $topology) {
            if (($permission = $this->mapTopologyToPermission($topology)) instanceof Permission) {
                $credential->grantPermission($permission);
            }
        }

        foreach ($from['action_rules'] as $actionRule) {
            foreach ($this->mapActionRuleToRoles($actionRule) as $role) {
                $credential->assignRole($role);
            }
        }

        return $credential;
    }

    private function mapTopologyToPermission(string $topology): ?Permission
    {
        if (! $permissionString = (self::LEGACY_PERMISSION_MAP[$topology] ?? null)) {
            @trigger_error(\sprintf('"%s" topology role is not mapped to any "%s", add it to "%s::LEGACY_PERMISSION_MAP".', $topology, Permission::class, self::class), \E_USER_DEPRECATED);

            return null;
        }

        return new Permission($permissionString);
    }

    /**
     * @return array<Role>
     */
    private function mapActionRuleToRoles(string $actionRule): array
    {
        // TODO add command ACLs
        $legacyRoles = match ($actionRule) {
            'host_schedule_check' => ['ROLE_HOST_CHECK'],
            'host_schedule_forced_check' => ['ROLE_HOST_CHECK', 'ROLE_HOST_FORCED_CHECK'],
            'service_schedule_check' => ['ROLE_SERVICE_CHECK'],
            'service_schedule_forced_check' => ['ROLE_SERVICE_CHECK', 'ROLE_SERVICE_FORCED_CHECK'],
            'host_acknowledgement' => ['ROLE_HOST_ACKNOWLEDGEMENT'],
            'host_disacknowledgement' => ['ROLE_HOST_DISACKNOWLEDGEMENT'],
            'service_acknowledgement' => ['ROLE_SERVICE_ACKNOWLEDGEMENT'],
            'service_disacknowledgement' => ['ROLE_SERVICE_DISACKNOWLEDGEMENT'],
            'service_schedule_downtime' => ['ROLE_ADD_SERVICE_DOWNTIME', 'ROLE_CANCEL_SERVICE_DOWNTIME'],
            'host_schedule_downtime' => ['ROLE_ADD_HOST_DOWNTIME', 'ROLE_CANCEL_HOST_DOWNTIME'],
            'service_submit_result' => ['ROLE_SERVICE_SUBMIT_RESULT'],
            'host_submit_result' => ['ROLE_HOST_SUBMIT_RESULT'],
            'host_comment' => ['ROLE_HOST_ADD_COMMENT'],
            'service_comment' => ['ROLE_SERVICE_ADD_COMMENT'],
            'service_display_command' => ['ROLE_DISPLAY_COMMAND'],
            'generate_cfg' => ['ROLE_GENERATE_CONFIGURATION'],
            'manage_tokens' => ['ROLE_MANAGE_TOKENS', 'ROLE_CREATE_EDIT_POLLER_CFG'],
            'create_edit_poller_cfg' => ['ROLE_CREATE_EDIT_POLLER_CFG'],
            'delete_poller_cfg' => ['ROLE_DELETE_POLLER_CFG'],
            'top_counter' => ['ROLE_DISPLAY_TOP_COUNTER'],
            'poller_stats' => ['ROLE_DISPLAY_TOP_COUNTER_POLLERS_STATISTICS'],
            default => [],
        };

        $roles = [];
        foreach ($legacyRoles as $legacyRole) {
            if (! $roleString = (self::LEGACY_ROLE_MAP[$legacyRole] ?? null)) { // @phpstan-ignore-line (while LEGACY_ROLE_MAP is empty)
                @trigger_error(\sprintf('"%s" role is not mapped to any "%s", add it to "%s::LEGACY_ROLE_MAP".', $actionRule, Role::class, self::class), \E_USER_DEPRECATED);

                continue;
            }

            $roles[] = new Role($roleString); // @phpstan-ignore-line (while LEGACY_ROLE_MAP is empty)
        }

        return $roles;
    }
}
