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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller;

use ApiPlatform\Metadata\ApiProperty;

/**
 * MON-215450 phase 2d: public CA of the central broker, for the poller's BBDO TLS trust (contracts.md § 8).
 */
final readonly class BrokerCaOutput
{
    public function __construct(
        #[ApiProperty(
            description: 'CA certificate (PEM) the poller uses to verify the central broker',
            openapiContext: ['example' => "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----\n"]
        )]
        public string $pem,

        #[ApiProperty(
            description: 'SHA-256 of the DER certificate, 64 lowercase hex characters',
            openapiContext: ['example' => '3f2a...e9']
        )]
        public string $fingerprintSha256,
    ) {
    }
}
