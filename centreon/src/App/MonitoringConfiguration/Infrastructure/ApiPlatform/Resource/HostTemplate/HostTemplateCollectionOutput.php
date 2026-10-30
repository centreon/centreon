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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;

/**
 * A host template as listed. The fields below the constructor come from sibling data bulk-fetched
 * for the whole page by the provider, then assigned onto the transformed output.
 */
final class HostTemplateCollectionOutput
{
    public int $linkedServiceTemplatesCount = 0;

    /** @var list<HostTemplateOutput> the direct parent templates, in inheritance order */
    public array $parentTemplates = [];

    /** the template's own icon, otherwise the first one inherited, otherwise null (then omitted from the payload, like any null) */
    public ?HostIconOutput $icon = null;

    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,

        public string $name,

        public ?string $alias,

        public bool $isLocked,
    ) {
    }
}
