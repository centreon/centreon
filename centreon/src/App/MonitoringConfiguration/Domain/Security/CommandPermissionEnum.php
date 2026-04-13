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

namespace App\MonitoringConfiguration\Domain\Security;

enum CommandPermissionEnum: string
{
    case CanReadChecks = 'can_read_command_checks';
    case CanReadAndWriteChecks = 'can_read_and_write_command_checks';
    case CanReadNotifications = 'can_read_command_notifications';
    case CanReadAndWriteNotifications = 'can_read_and_write_command_notifications';
    case CanReadMiscellaneous = 'can_read_command_miscellaneous';
    case CanReadAndWriteMiscellaneous = 'can_read_and_write_command_miscellaneous';
    case CanReadDiscovery = 'can_read_command_discovery';
    case CanReadAndWriteDiscovery = 'can_read_and_write_command_discovery';
}
