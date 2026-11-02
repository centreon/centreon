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

namespace App\ActivityLogging\Domain\Aggregate;

enum TargetTypeEnum: string
{
    case Command = 'Command';
    case TimePeriod = 'TimePeriod';
    case Contact = 'Contact';
    case ContactGroup = 'ContactGroup';
    case Host = 'Host';
    case HostTemplate = 'HostTemplate';
    case HostGroup = 'HostGroup';
    case Service = 'Service';
    case ServiceGroup = 'ServiceGroup';
    case Trap = 'Trap';
    case Escalation = 'Escalation';
    case HostDependency = 'HostDependency';
    case HostGroupDependency = 'HostGroupDependency';
    case ServiceDependency = 'ServiceDependency';
    case ServiceGroupDependency = 'ServiceGroupDependency';
    case Poller = 'Poller';
    case Engine = 'Engine';
    case Broker = 'Broker';
    case Resource = 'Resource';
    case MetaService = 'MetaService';
    case AccessGroup = 'AccessGroup';
    case MenuAccess = 'MenuAccess';
    case ResourceAccess = 'ResourceAccess';
    case ActionAccess = 'ActionAccess';
    case Manufacturer = 'Manufacturer';
    case HostCategory = 'HostCategory';
    case ServiceCategory = 'ServiceCategory';
    case HostSeverity = 'HostSeverity';
    case ServiceSeverity = 'ServiceSeverity';
}
