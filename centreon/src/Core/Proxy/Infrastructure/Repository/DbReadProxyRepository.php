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

declare(strict_types = 1);

namespace Core\Proxy\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Proxy\Application\Repository\ReadProxyRepositoryInterface;
use Core\Proxy\Domain\Model\Proxy;

class DbReadProxyRepository extends AbstractRepositoryRDB implements ReadProxyRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function getProxy(): ?Proxy
    {
        $statement = $this->db->query($this->translateDbName(
            <<<'SQL'
                SELECT `key`, `value` FROM `:db`.`options` WHERE `key` LIKE 'proxy%';
                SQL
        ));
        /**
         * @var array{
         *  proxy_url:string|null,
         *  proxy_port:string|null,
         *  proxy_user: string|null,
         *  proxy_password: string|null
         * }|false $proxyInfo
         */
        $proxyInfo = $statement === false ? false : $statement->fetchAll(\PDO::FETCH_KEY_PAIR);

        if (isset($proxyInfo['proxy_url']) && $proxyInfo['proxy_url'] !== '') {
            $port = isset($proxyInfo['proxy_port']) ? (int) $proxyInfo['proxy_port'] : null;

            return new Proxy(
                $proxyInfo['proxy_url'],
                $port,
                $proxyInfo['proxy_user'],
                $proxyInfo['proxy_password'],
            );
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function hasProxy(): bool
    {
        $statement = $this->db->query($this->translateDbName(
            <<<'SQL'
                SELECT 1 FROM `:db`.`options`
                WHERE `key` = 'proxy_url'
                SQL
        ));

        return $statement && $statement->fetchColumn();
    }
}
