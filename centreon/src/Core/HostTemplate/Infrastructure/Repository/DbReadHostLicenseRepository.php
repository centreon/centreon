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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\LegacyContainer;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\HostType;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\HostTemplate\Application\Repository\ReadHostLicenseRepositoryInterface;

/**
 * Reads the license through the license manager module, the way the legacy
 * CentreonHost::isAllowed() does: the module is a legacy service, only reachable from the
 * legacy container.
 */
final class DbReadHostLicenseRepository extends DatabaseRepository implements ReadHostLicenseRepositoryInterface
{
    use SqlMultipleBindTrait;
    private const LICENSE_MANAGER_MODULE = 'centreon-license-manager';
    private const PLUGIN_PACK_MANAGER_MODULE = 'centreon-pp-manager';
    private const LICENSE_PRODUCT = 'epp';
    private const UNLIMITED_HOSTS = -1;

    /** Monitoring Connectors free of license, whose templates stay usable once the host limit is reached. */
    private const FREE_CONNECTOR_SLUGS = [
        'applications-databases-mysql',
        'applications-monitoring-centreon-central',
        'applications-monitoring-centreon-database',
        'applications-monitoring-centreon-poller',
        'base-generic',
        'hardware-printers-standard-rfc3805-snmp',
        'hardware-ups-standard-rfc1628-snmp',
        'network-cisco-standard-snmp',
        'operatingsystems-linux-snmp',
        'operatingsystems-windows-snmp',
    ];

    private mixed $license = null;

    private bool $isLicenseResolved = false;

    /**
     * @inheritDoc
     */
    public function isValid(): bool
    {
        $license = $this->getLicense();
        if ($license === null) {
            return false;
        }

        try {
            return (bool) $license->validate();
        } catch (\Throwable $ex) {
            throw new RepositoryException('Cannot validate the license', [], $ex);
        }
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
            throw new RepositoryException('Cannot read the license data', [], $ex);
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
        try {
            $count = $this->connection->fetchOne(
                $this->translateDbName('SELECT COUNT(*) FROM `:db`.host WHERE host_register = :hostType'),
                QueryParameters::create([QueryParameter::string('hostType', HostType::Host->value)])
            );
        } catch (ConnectionException|CollectionException|ValueObjectException $ex) {
            throw new RepositoryException("Cannot count the hosts: {$ex->getMessage()}", [], $ex);
        }

        return (int) $count;
    }

    /**
     * @inheritDoc
     */
    public function findPaidConnectorTemplateIds(): array
    {
        try {
            if (! $this->isModuleInstalled(self::PLUGIN_PACK_MANAGER_MODULE)) {
                return [];
            }

            [$bindValues, $freeSlugPlaceholders] = $this->createMultipleBindQuery(
                self::FREE_CONNECTOR_SLUGS,
                ':freeSlug_'
            );
            $queryParameters = QueryParameters::create([
                QueryParameter::string('hostTemplateType', HostType::Template->value),
            ]);
            foreach ($bindValues as $key => $slug) {
                /** @var string $slug */
                $queryParameters->add($key, QueryParameter::string($key, $slug));
            }

            $ids = $this->connection->fetchFirstColumn(
                $this->translateDbName(
                    <<<SQL
                        WITH RECURSIVE unlicensed (id) AS (
                            SELECT ph.host_id
                            FROM `:db`.mod_ppm_pluginpack_host ph
                            INNER JOIN `:db`.mod_ppm_pluginpack pp ON pp.pluginpack_id = ph.pluginpack_id
                            WHERE pp.slug NOT IN ({$freeSlugPlaceholders})
                            UNION
                            SELECT htr.host_host_id
                            FROM `:db`.host_template_relation htr
                            INNER JOIN unlicensed u ON u.id = htr.host_tpl_id
                        )
                        SELECT h.host_id
                        FROM `:db`.host h
                        INNER JOIN unlicensed u ON u.id = h.host_id
                        WHERE h.host_register = :hostTemplateType
                        ORDER BY h.host_id
                        SQL
                ),
                $queryParameters
            );
        } catch (ConnectionException|CollectionException|ValueObjectException $ex) {
            throw new RepositoryException(
                "Cannot find the host templates of the paid connectors: {$ex->getMessage()}",
                [],
                $ex
            );
        }

        return array_map('intval', $ids);
    }

    /**
     * The license service is a factory of the legacy container: it is built from the product name and the
     * host check set in the container at the time it is resolved.
     *
     * @throws RepositoryException
     */
    private function getLicense(): mixed
    {
        if (! $this->isLicenseResolved) {
            $this->license = $this->resolveLicense();
            $this->isLicenseResolved = true;
        }

        return $this->license;
    }

    /**
     * @throws RepositoryException
     */
    private function resolveLicense(): mixed
    {
        try {
            if (! $this->isModuleInstalled(self::LICENSE_MANAGER_MODULE)) {
                return null;
            }

            $container = LegacyContainer::getInstance();
            if (! isset($container['lm.license'])) {
                return null;
            }

            $container['lm.productName'] = self::LICENSE_PRODUCT;
            $container['lm.hostCheck'] = true;
            $license = $container['lm.license'];
        } catch (ConnectionException|CollectionException|ValueObjectException $ex) {
            throw new RepositoryException("Cannot look for the license manager: {$ex->getMessage()}", [], $ex);
        } catch (\Throwable $ex) {
            throw new RepositoryException('Cannot build the license', [], $ex);
        }

        return is_object($license) ? $license : null;
    }

    /**
     * @param string $moduleName
     *
     * @throws ConnectionException|CollectionException|ValueObjectException
     * @return bool
     */
    private function isModuleInstalled(string $moduleName): bool
    {
        return $this->connection->fetchOne(
            $this->translateDbName('SELECT 1 FROM `:db`.modules_informations WHERE `name` = :moduleName'),
            QueryParameters::create([QueryParameter::string('moduleName', $moduleName)])
        ) !== false;
    }
}
