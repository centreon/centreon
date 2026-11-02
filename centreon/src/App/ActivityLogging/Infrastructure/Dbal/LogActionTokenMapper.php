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

namespace App\ActivityLogging\Infrastructure\Dbal;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;

/**
 * Maps the domain enums to the tokens stored in the legacy `log_action` table.
 *
 * Some target types were written under several tokens over time (e.g. `command` by the Core
 * writer and `commands` by the legacy one): the first token is the one written, all of them
 * are read and filtered on.
 */
final class LogActionTokenMapper
{
    /**
     * @var array<value-of<TargetTypeEnum>, non-empty-list<string>>
     */
    private const TARGET_TYPE_TOKENS = [
        TargetTypeEnum::Command->value => ['command', 'commands'],
        TargetTypeEnum::TimePeriod->value => ['timeperiod'],
        TargetTypeEnum::Contact->value => ['contact'],
        TargetTypeEnum::ContactGroup->value => ['contactgroup'],
        // host templates share the host token: a stored 'host' row is read back as Host (first match)
        TargetTypeEnum::Host->value => ['host'],
        TargetTypeEnum::HostTemplate->value => ['host'],
        TargetTypeEnum::HostGroup->value => ['hostgroup'],
        TargetTypeEnum::Service->value => ['service'],
        TargetTypeEnum::ServiceGroup->value => ['servicegroup'],
        TargetTypeEnum::Trap->value => ['traps'],
        TargetTypeEnum::Escalation->value => ['escalation'],
        TargetTypeEnum::HostDependency->value => ['host dependency'],
        TargetTypeEnum::HostGroupDependency->value => ['hostgroup dependency'],
        TargetTypeEnum::ServiceDependency->value => ['service dependency'],
        TargetTypeEnum::ServiceGroupDependency->value => ['servicegroup dependency'],
        TargetTypeEnum::Poller->value => ['poller'],
        TargetTypeEnum::Engine->value => ['engine'],
        TargetTypeEnum::Broker->value => ['broker'],
        // the legacy resource form writes the singular token, ActionLog::OBJECT_TYPE_RESOURCES is plural
        TargetTypeEnum::Resource->value => ['resources', 'resource'],
        TargetTypeEnum::MetaService->value => ['meta'],
        TargetTypeEnum::AccessGroup->value => ['access group'],
        TargetTypeEnum::MenuAccess->value => ['menu access'],
        TargetTypeEnum::ResourceAccess->value => ['resource access'],
        TargetTypeEnum::ActionAccess->value => ['action access'],
        TargetTypeEnum::Manufacturer->value => ['manufacturer'],
        TargetTypeEnum::HostCategory->value => ['hostcategories'],
        TargetTypeEnum::ServiceCategory->value => ['servicecategories'],
        TargetTypeEnum::HostSeverity->value => ['hostseverity'],
        TargetTypeEnum::ServiceSeverity->value => ['serviceseverity'],
    ];

    /**
     * @var array<value-of<ActionEnum>, string>
     */
    private const ACTION_TOKENS = [
        ActionEnum::Add->value => 'a',
        ActionEnum::Update->value => 'c',
        ActionEnum::Delete->value => 'd',
        ActionEnum::Enable->value => 'enable',
        ActionEnum::Disable->value => 'disable',
        ActionEnum::MassChange->value => 'mc',
    ];

    /**
     * The token written for a new log.
     */
    public static function targetTypeToToken(TargetTypeEnum $type): string
    {
        return self::TARGET_TYPE_TOKENS[$type->value][0];
    }

    /**
     * Every token a target type may have been stored under.
     *
     * @return non-empty-list<string>
     */
    public static function targetTypeToTokens(TargetTypeEnum $type): array
    {
        return self::TARGET_TYPE_TOKENS[$type->value];
    }

    /**
     * Every token mapped to a target type.
     *
     * @return non-empty-list<string>
     */
    public static function allTargetTypeTokens(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::TARGET_TYPE_TOKENS))));
    }

    public static function tokenToTargetType(string $token): ?TargetTypeEnum
    {
        foreach (self::TARGET_TYPE_TOKENS as $type => $tokens) {
            if (in_array($token, $tokens, true)) {
                return TargetTypeEnum::from($type);
            }
        }

        return null;
    }

    public static function actionToToken(ActionEnum $action): string
    {
        return self::ACTION_TOKENS[$action->value];
    }

    /**
     * @return non-empty-list<string>
     */
    public static function allActionTokens(): array
    {
        return array_values(self::ACTION_TOKENS);
    }

    public static function tokenToAction(string $token): ?ActionEnum
    {
        $action = array_search($token, self::ACTION_TOKENS, true);

        return $action === false ? null : ActionEnum::from($action);
    }
}
