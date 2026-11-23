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

namespace App\MonitoringConfiguration\Domain\Service;

interface BrokerCaProvider
{
    /**
     * MON-215450 phase 2d (contracts.md § 8): public CA of the central broker, handed out at poller creation.
     * ca and warning are both null when no central broker API is configured. Never throws for an unreachable
     * central broker: the warning says why.
     *
     * @return array{ca: array{pem: string, fingerprint_sha256: string}|null, warning: string|null}
     */
    public function read(): array;
}
