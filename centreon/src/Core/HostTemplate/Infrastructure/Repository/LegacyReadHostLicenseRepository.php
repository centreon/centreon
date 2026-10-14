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

namespace Core\HostTemplate\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\LegacyContainer;
use Core\Common\Domain\HostType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\HostTemplate\Application\Repository\ReadHostLicenseRepositoryInterface;

/**
 * Reads the license through the license manager module, the way the legacy
 * CentreonHost::isAllowed() does: the module is a legacy service, only reachable from the
 * legacy container.
 */
final class LegacyReadHostLicenseRepository extends AbstractRepositoryRDB implements ReadHostLicenseRepositoryInterface
{
    use LoggerTrait;
    private const LICENSE_MANAGER_MODULE = 'centreon-license-manager';
    private const LICENSE_PRODUCT = 'epp';
    private const UNLIMITED_HOSTS = -1;

    private mixed $license = null;

    private bool $isLicenseResolved = false;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function isValid(): bool
    {
        $license = $this->getLicense();

        return $license !== null && (bool) $license->validate();
    }

    /**
     * @inheritDoc
     */
    public function getMaxHosts(): ?int
    {
        $license = $this->getLicense();
        if ($license === null) {
            return 0;
        }

        try {
            $data = $license->getData();
        } catch (\Throwable $ex) {
            $this->error('Cannot read the license data', ['exception' => $ex->getMessage()]);

            return 0;
        }

        $hosts = is_array($data) && is_array($data['licensing'] ?? null) ? ($data['licensing']['hosts'] ?? null) : null;
        $maxHosts = is_numeric($hosts) ? (int) $hosts : 0;

        return $maxHosts === self::UNLIMITED_HOSTS ? null : $maxHosts;
    }

    /**
     * @inheritDoc
     */
    public function countHosts(): int
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                <<<'SQL'
                    SELECT COUNT(*) FROM `:db`.host WHERE host_register = :hostType
                    SQL
            )
        );
        $statement->bindValue(':hostType', HostType::Host->value, \PDO::PARAM_STR);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * The license service is a factory of the legacy container: it is built from the product name and the
     * host check set in the container at the time it is resolved.
     *
     * @throws \Throwable
     */
    private function getLicense(): mixed
    {
        if (! $this->isLicenseResolved) {
            $this->isLicenseResolved = true;
            $this->license = $this->resolveLicense();
        }

        return $this->license;
    }

    /**
     * @throws \Throwable
     */
    private function resolveLicense(): mixed
    {
        $statement = $this->db->prepare(
            $this->translateDbName('SELECT 1 FROM `:db`.modules_informations WHERE `name` = :moduleName')
        );
        $statement->bindValue(':moduleName', self::LICENSE_MANAGER_MODULE, \PDO::PARAM_STR);
        $statement->execute();
        if ($statement->fetchColumn() === false) {
            return null;
        }

        $container = LegacyContainer::getInstance();
        if (! isset($container['lm.license'])) {
            return null;
        }

        $container['lm.productName'] = self::LICENSE_PRODUCT;
        $container['lm.hostCheck'] = true;
        $license = $container['lm.license'];

        return is_object($license) ? $license : null;
    }
}
