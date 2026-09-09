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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

/**
 * @extends AggregateRoot<HostId>
 */
final class Host extends AggregateRoot
{
    /**
     * @param Collection<HostTemplateId> $templateIds
     */
    public function __construct(
        ?HostId $id,
        public readonly HostName $name,
        public readonly ?HostAlias $alias,
        public readonly HostAddress $address,
        public readonly bool $activated,
        public readonly PollerId $pollerId,
        public readonly Collection $templateIds,
    ) {
        parent::__construct($id);
    }
}
