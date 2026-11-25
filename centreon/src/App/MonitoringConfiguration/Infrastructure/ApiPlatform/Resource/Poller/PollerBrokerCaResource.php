<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\GetPollerBrokerCaProvider;

/**
 * MON-215450 phase 2g (contracts.md § 11.W): public CA of the central broker, fetched by install.sh with the poller
 * token (no user session). The script trusts it only after comparing its fingerprint with the one in the
 * installation command.
 */
#[ApiResource(
    operations: [new Get(
        uriTemplate: '/configuration/pollers/{uid}/broker-ca',
        provider: GetPollerBrokerCaProvider::class,
        openapi: new Model\Operation(
            tags: ['Poller'],
            summary: 'Retrieves the central broker CA for a poller installation',
            description: 'Authenticated by a poller token (X-Centreon-Poller-Token: <token_name>:<token_value>), not by a user session. Used by install.sh for broker-channel Docker pollers.',
            parameters: [
                new Model\Parameter(
                    name: 'uid',
                    in: 'path',
                    description: 'Uid of the poller being installed.',
                    required: true,
                    schema: ['type' => 'integer'],
                ),
                new Model\Parameter(
                    name: 'X-Centreon-Poller-Token',
                    in: 'header',
                    description: 'Poller token, as <token_name>:<token_value>.',
                    required: true,
                    schema: ['type' => 'string'],
                ),
            ],
            responses: [
                401 => new Model\Response('Missing, invalid, revoked or expired poller token'),
                404 => new Model\Response('No poller with this uid'),
                503 => new Model\Response('Central broker CA unavailable'),
            ],
            security: [],
        ),
    )],
)]
final readonly class PollerBrokerCaResource
{
    public function __construct(
        #[ApiProperty(description: 'Uid of the poller', identifier: true)]
        public int $uid,

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
