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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * The `check_options` sub-object of a service.
 */
final readonly class ServiceCheckOptionsOutput
{
    /**
     * @param list<string> $args ordered check-command arguments
     * @param list<ServiceMacroOutput> $macros every macro the service effectively has: its own, then the ones it
     *                                         inherits
     */
    public function __construct(
        public ?ServiceCheckCommandOutput $command,
        #[Sensitive] public array $args,
        public array $macros,
    ) {
    }
}
