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

use App\MonitoringConfiguration\Domain\Service\GorgoneNodesSynchronizer;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Centreon\Domain\Gorgone\Command\NodesSync;
use Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface;
use Webmozart\Assert\Assert;

/**
 * Sends `centreon::nodes::sync` through the legacy Gorgone client, the single implementation
 * of the Gorgone API protocol on the platform (also used by the legacy poller form and the
 * remote server wizard).
 *
 * The legacy service is resolved per call rather than in the constructor so that injecting
 * this adapter never boots the legacy kernel on its own.
 */
final readonly class LegacyGorgoneNodesSynchronizer implements GorgoneNodesSynchronizer
{
    public function __construct(
        private LegacyContainer $legacyContainer,
    ) {
    }

    public function synchronize(): void
    {
        $gorgoneService = $this->legacyContainer->get(GorgoneServiceInterface::class);
        Assert::isInstanceOf($gorgoneService, GorgoneServiceInterface::class);

        $gorgoneService->send(new NodesSync());
    }
}
