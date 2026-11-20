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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Event\AsEventHandler;
use Webmozart\Assert\Assert;

/**
 * Any template change makes the inheriting hosts' pollers regenerate, whether it is logged or not.
 */
#[AsEventHandler]
final readonly class FlagHostTemplateInheritorsPollersEventHandler
{
    public function __construct(
        private HostTemplateRepository $hostTemplateRepository,
        private PollerRepository $pollerRepository,
    ) {
    }

    public function __invoke(HostTemplateUpdated $event): void
    {
        $template = $event->aggregate;
        Assert::isInstanceOf($template, HostTemplate::class);

        foreach ($this->hostTemplateRepository->findPollerIdsOfInheritingHosts($template->id()) as $pollerId) {
            $this->pollerRepository->flagIdAsChanged($pollerId);
        }
    }
}
