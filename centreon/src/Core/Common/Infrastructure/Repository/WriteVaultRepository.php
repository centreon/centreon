<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\Repository;

use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Utility\Interfaces\UUIDGeneratorInterface;

class WriteVaultRepository extends AbstractVaultRepository implements WriteVaultRepositoryInterface
{
    public function __construct(
        private UUIDGeneratorInterface $uuidGenerator,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function upsert(?string $uuid = null, array $inserts = [], array $deletes = []): string
    {
        if ($this->vaultConfiguration === null) {
            throw new \LogicException('Vault not configured');
        }

        if ($uuid === null) {
            $uuid = $this->uuidGenerator->generateV4();
        }

        $url = $this->buildUrl($uuid);

        // Retrieve current vault data
        $responseContent = $this->sendRequest('GET', $url);
        $responseContent = json_decode($responseContent ?? '', true);
        $payload = [];
        if (is_array($responseContent) && isset($responseContent['data']['data'])) {
            $payload = $responseContent['data']['data'];
        }

        // Delete unwanted data
        foreach ($deletes as $deleteKey => $deleteValue) {
            unset($payload[$deleteKey]);
        }
        // Add new data
        foreach ($inserts as $insertKey => $insertValue) {
            $payload[$insertKey] = $insertValue;
        }

        $this->sendRequest('POST', $url, $payload);

        return $uuid;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): void
    {
        if ($this->vaultConfiguration === null) {
            throw new \LogicException('Vault not configured');
        }

        $url = $this->vaultConfiguration->getAddress() . ':' . $this->vaultConfiguration->getPort()
            . '/v1/' . $this->vaultConfiguration->getRootPath() . '/metadata/' . $this->customPath . $uuid;
        $url = sprintf('%s://%s', parent::DEFAULT_SCHEME, $url);

        $this->sendRequest('DELETE', $url);
    }
}