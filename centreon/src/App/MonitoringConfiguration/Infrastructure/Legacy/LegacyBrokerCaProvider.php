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

namespace App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Service\BrokerCaProvider;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Core\RemoteChannel\Infrastructure\Broker\BrokerCaReader;
use Webmozart\Assert\Assert;

/**
 * Resolves the legacy reader per call, never in the constructor: LegacyContainer boots the legacy kernel on first
 * use and is only spared by its #[Lazy] proxy.
 */
final readonly class LegacyBrokerCaProvider implements BrokerCaProvider
{
    public function __construct(
        private LegacyContainer $legacyContainer,
    ) {
    }

    public function read(): array
    {
        $reader = $this->legacyContainer->get(BrokerCaReader::class);
        Assert::isInstanceOf($reader, BrokerCaReader::class);

        return $reader->read();
    }
}
