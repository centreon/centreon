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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;

/**
 * Once the license host limit is reached, a service can no longer be given a template coming from a paid
 * Monitoring Connector.
 */
final readonly class ServiceTemplateLicenseGuard
{
    public function __construct(
        private UnlicensedServiceTemplates $unlicensedServiceTemplates,
    ) {
    }

    /**
     * @throws UnlicensedServiceTemplatesException
     */
    public function assertLicensed(?ServiceTemplateId $templateId): void
    {
        if (! $templateId instanceof ServiceTemplateId) {
            return;
        }

        if (array_any(
            $this->unlicensedServiceTemplates->findIds()->toArray(),
            static fn (ServiceTemplateId $unlicensedId): bool => $unlicensedId->value === $templateId->value,
        )) {
            throw new UnlicensedServiceTemplatesException($templateId->value);
        }
    }
}
