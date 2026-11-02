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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\Shared\Domain\Exception\AggregateConflictException;

/**
 * A service needs a check command, set on it or provided by its service template chain. Reported against
 * `serviceTemplateId` when the template chain provides none, against `checkOptions` when there is no template.
 */
final class ServiceCheckCommandMissingException extends AggregateConflictException
{
    /**
     * @param ?int $templateId the service template whose chain provides no check command, null when the service
     *                         has no template
     */
    public function __construct(?int $templateId)
    {
        if ($templateId === null) {
            parent::__construct(['checkOptions' => ['commandId']], 'A check command is required when no service template is set.');

            return;
        }

        parent::__construct(['serviceTemplateId' => $templateId], 'The selected service template does not provide any check command.');
    }
}
