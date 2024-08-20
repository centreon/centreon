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

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;

class ReadVaultRepository extends AbstractVaultRepository implements ReadVaultRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findFromPath(string $path): array
    {
        if ($this->vaultConfiguration === null) {
            throw new \LogicException('Vault not configured');
        }
        $customPathElements = explode('::', $path);
        $customPath = end($customPathElements);
        $url = $this->vaultConfiguration->getAddress() . ':' . $this->vaultConfiguration->getPort()
            . '/v1/' . $customPath;
        $url = sprintf('%s://%s', parent::DEFAULT_SCHEME, $url);

        $responseContent = $this->sendRequest('GET', $url);
        if (is_array($responseContent) && isset($responseContent['data']['data'])) {
            return $responseContent['data']['data'];
        }

        return [];
    }
}
