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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Service\UnlicensedHostTemplates;
use App\Shared\Domain\Collection;
use App\Shared\Domain\License\HostLicenseLimitInterface;
use App\Shared\Domain\License\LicenseCheckInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Legacy CentreonHost::getLimitedList(), which hides these templates from the host form.
 *
 * The license is read through the license manager module, which provides both license services:
 * without them (module absent) the limit is taken as reached, as legacy does. The Monitoring
 * Connectors come from the plugin pack manager module: without it, no template is restricted.
 */
final readonly class DbalUnlicensedHostTemplates implements UnlicensedHostTemplates
{
    /** The license product the host limit is read from. */
    public const LICENSE_PRODUCT = 'epp';

    /** Monitoring Connectors free of license, whose templates stay usable once the limit is reached. */
    public const FREE_CONNECTOR_SLUGS = [
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
    private const PLUGIN_PACK_MANAGER_MODULE = 'centreon-pp-manager';
    private const LICENSE_MANAGER_MODULE = 'centreon-license-manager';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private ?LicenseCheckInterface $licenseCheck = null,
        private ?HostLicenseLimitInterface $hostLicenseLimit = null,
    ) {
    }

    public function findIds(): Collection
    {
        if (! $this->isModuleInstalled(self::PLUGIN_PACK_MANAGER_MODULE) || ! $this->isHostLimitReached()) {
            return new Collection([], HostTemplateId::class);
        }

        // The connector templates, then every template inheriting from them (legacy getHostChain()).
        // UNION dedupes the reached ids, so a template loop already in the data cannot make the
        // recursion run forever.
        $sql = <<<'SQL'
            WITH RECURSIVE unlicensed (id) AS (
                SELECT ph.host_id
                FROM mod_ppm_pluginpack_host ph
                INNER JOIN mod_ppm_pluginpack pp ON pp.pluginpack_id = ph.pluginpack_id
                WHERE pp.slug NOT IN (:freeSlugs)
                UNION
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN unlicensed u ON u.id = htr.host_tpl_id
            )
            SELECT h.host_id
            FROM host h
            INNER JOIN unlicensed u ON u.id = h.host_id
            WHERE h.host_register = '0'
            ORDER BY h.host_id
            SQL;

        /** @var list<int|string> $ids */
        $ids = $this->connection->executeQuery(
            $sql,
            ['freeSlugs' => self::FREE_CONNECTOR_SLUGS],
            ['freeSlugs' => ArrayParameterType::STRING],
        )->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $id): HostTemplateId => new HostTemplateId((int) $id), $ids),
            HostTemplateId::class,
        );
    }

    /**
     * Legacy CentreonHost::isAllowed(): reached unless the license is valid and allows more hosts
     * than the platform has, whether they are enabled or not.
     */
    private function isHostLimitReached(): bool
    {
        if (
            ! $this->licenseCheck instanceof LicenseCheckInterface
            || ! $this->hostLicenseLimit instanceof HostLicenseLimitInterface
            || ! $this->isModuleInstalled(self::LICENSE_MANAGER_MODULE)
            || ! $this->licenseCheck->isValid(self::LICENSE_PRODUCT)
        ) {
            return true;
        }

        $maxHosts = $this->hostLicenseLimit->getMaxHosts(self::LICENSE_PRODUCT);

        return $maxHosts !== null && $maxHosts <= $this->countHosts();
    }

    private function countHosts(): int
    {
        /** @var int|string $count */
        $count = $this->connection->fetchOne("SELECT COUNT(*) FROM host WHERE host_register = '1'");

        return (int) $count;
    }

    private function isModuleInstalled(string $name): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM modules_informations WHERE name = :name',
            ['name' => $name],
        ) !== false;
    }
}
