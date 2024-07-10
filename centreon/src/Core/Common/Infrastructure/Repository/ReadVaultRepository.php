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

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Security\Vault\Domain\Model\NewVaultConfiguration;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

class ReadVaultRepository extends AbstractVaultRepository implements ReadVaultRepositoryInterface
{
    use LoggerTrait;

    /**
     * @inheritDoc
     */
    public function findFromPath(string $path): array
    {
        if ($this->vaultConfiguration === null) {
            throw new \LogicException('Vault not configured');
        }
        $customPathElements = explode('::', $path);

        // remove vault key from path
        array_pop($customPathElements);

        // Keep only the uri from the path
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

    public function testVaultConnection(VaultConfiguration|NewVaultConfiguration $vaultConfiguration): bool
    {
        try {
            $url = $vaultConfiguration->getAddress() . ':'
                . $vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $url = sprintf('%s://%s', parent::DEFAULT_SCHEME, $url);
            $body = [
                'role_id' => $vaultConfiguration->getRoleId(),
                'secret_id' => $vaultConfiguration->getSecretId(),
            ];
            $loginResponse = $this->httpClient->request('POST', $url, ['json' => $body]);

            $content = json_decode($loginResponse->getContent(), true);
        } catch (\Exception $ex) {
            $this->error('Could not login to vault');

            return false;
        }

        /** @var array{auth?:array{client_token?:string}} $content */
        return (bool) (isset($content['auth']['client_token']));
    }
}
