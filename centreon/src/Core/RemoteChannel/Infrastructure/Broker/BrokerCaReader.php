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

namespace Core\RemoteChannel\Infrastructure\Broker;

use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Psr\Log\LoggerInterface;

/**
 * MON-215450 phase 2d (contracts.md § 8): reads the public CA of the central broker (GET /v1/ca), handed out at
 * poller creation so the poller trusts the central without any out-of-band file. Public material only.
 */
class BrokerCaReader
{
    public function __construct(
        private readonly BrokerApiClient $client,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * ca and warning are both null when no central broker API is configured (gorgone-only platform).
     *
     * @return array{ca: array{pem: string, fingerprint_sha256: string}|null, warning: string|null}
     */
    public function read(): array
    {
        if (! $this->client->isConfigured()) {
            return ['ca' => null, 'warning' => null];
        }

        try {
            $answer = $this->client->call('GET', '/v1/ca');
        } catch (RemoteChannelException $ex) {
            return $this->unavailable($ex->getMessage());
        }

        $pem = $answer['pem'] ?? null;
        $fingerprint = $answer['fingerprint_sha256'] ?? null;
        if (
            ! is_string($pem)
            || ! str_contains($pem, '-----BEGIN CERTIFICATE-----')
            || ! is_string($fingerprint)
            || preg_match('/^[0-9a-f]{64}$/', $fingerprint) !== 1
        ) {
            return $this->unavailable('GET /v1/ca: invalid answer');
        }

        return ['ca' => ['pem' => $pem, 'fingerprint_sha256' => $fingerprint], 'warning' => null];
    }

    /**
     * @return array{ca: null, warning: string}
     */
    private function unavailable(string $detail): array
    {
        $this->logger?->warning('Central broker CA unavailable at poller creation', ['detail' => $detail]);

        return [
            'ca' => null,
            'warning' => 'Poller created, but the central broker CA could not be read (' . $detail
                . '): fetch it later from the central broker before starting the poller.',
        ];
    }
}
