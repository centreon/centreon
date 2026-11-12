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

namespace App\MonitoringConfiguration\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ServiceCommandInheritance extends Constraint
{
    public string $missingCommandMessage = 'A check command is required when no service template is set.';

    public string $templateWithoutCommandMessage = 'The selected service template does not provide any check command.';

    public string $argumentsWithoutCommandMessage = 'Check command arguments require a check command to be set.';

    public string $eventHandlerArgumentsWithoutHandlerMessage = 'Event handler arguments require an event handler, set on the service or provided by its service template.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
