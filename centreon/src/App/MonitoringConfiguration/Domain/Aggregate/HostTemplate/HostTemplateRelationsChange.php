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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\NoValue;

/**
 * The relations a write gives a template, each one whole: NoValue keeps the current one.
 */
final readonly class HostTemplateRelationsChange
{
    /**
     * @param NoValue|Collection<HostTemplateId> $parentTemplateIds in inheritance order
     * @param NoValue|Collection<HostCategoryId> $categoryIds
     * @param NoValue|Collection<ServiceTemplateId> $linkedServiceTemplateIds in relation order
     * @param NoValue|Collection<NotificationContactId> $contactIds
     * @param NoValue|Collection<ContactGroupId> $contactGroupIds
     */
    public function __construct(
        public NoValue|Collection $parentTemplateIds = new NoValue(),
        public NoValue|Collection $categoryIds = new NoValue(),
        public NoValue|Collection $linkedServiceTemplateIds = new NoValue(),
        public NoValue|Collection $contactIds = new NoValue(),
        public NoValue|Collection $contactGroupIds = new NoValue(),
    ) {
    }
}
