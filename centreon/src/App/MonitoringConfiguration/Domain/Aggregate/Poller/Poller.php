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

namespace App\MonitoringConfiguration\Domain\Aggregate\Poller;

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

/**
 * @extends AggregateRoot<PollerId>
 */
final class Poller extends AggregateRoot
{
    /**
     * @param Collection<GlobalMacro> $globalMacros
     * @param Collection<PollerCommand> $pollerCommands
     */
    public function __construct(
        ?PollerId $id,
        public readonly PollerName $name,
        public readonly PollerAddress $address,
        public readonly bool $isCentral,
        public readonly bool $isDefault,
        public readonly bool $isActivated,
        public readonly PollerTypeEnum $pollerType,
        public readonly PollerUid $uid,
        public readonly Collection $globalMacros,
        public readonly GorgoneConfiguration $gorgoneConfiguration,
        public readonly EngineInformation $engineInformation,
        public readonly BrokerInformation $brokerInformation,
        public readonly ConnectorConfiguration $connectorConfiguration,
        public readonly TrapConfiguration $trapConfiguration,
        public readonly Collection $pollerCommands,
        public readonly ?CentralAddress $centralAddress = null,
        public ?PollerCMACertificates $cmaCertificates = null,
        public readonly ?PollerRemoteAttachment $remoteAttachment = null,
    ) {
        parent::__construct($id);
    }

    public function addGlobalMacro(GlobalMacro $globalMacro): void
    {
        if ($this->globalMacros->contains($globalMacro)) {
            return;
        }

        $this->globalMacros->add($globalMacro);
        $globalMacro->addPoller($this);
    }

    public function addPollerCMACertificates(PollerCMACertificates $cmaCertificates): void
    {
        $this->cmaCertificates = $cmaCertificates;
    }
}
