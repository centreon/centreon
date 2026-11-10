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

use Core\Host\Application\InheritanceManager;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\ServiceInheritance;

require_once __DIR__ . '/abstract/host.class.php';
require_once __DIR__ . '/abstract/service.class.php';

/**
 * Class
 *
 * @class Host
 */
class Host extends AbstractHost
{
    public const VERTICAL_NOTIFICATION = 1;
    public const CLOSE_NOTIFICATION = 2;
    public const CUMULATIVE_NOTIFICATION = 3;

    /** @var array */
    protected $hosts_by_name = [];

    /** @var array|null */
    protected $hosts = null;

    /**
     * host_id => host_name for every host of the poller, built once upfront
     * (MON-214629): the only host attribute read once a host's own batch is
     * done (getParents() for a parent in another batch, and the tail steps
     * Dependency/Escalation/generateIndexData) is its name.
     *
     * @var array<int, string>|null
     */
    protected $hostNamesById = null;

    /** Default number of hosts processed per batch (MON-214629). */
    private const DEFAULT_HOST_BATCH_SIZE = 2000;

    /** @var string */
    protected $generate_filename = 'hosts.cfg';

    /** @var string */
    protected string $object_name = 'host';

    /** @var array */
    protected $generated_parentship = [];

    /** @var array */
    protected $generatedHosts = [];

    /**
     * @param $host_id
     *
     * @return mixed
     */
    public function getSeverityForService($host_id)
    {
        return $this->hosts[$host_id]['severity_id_for_services'];
    }

    /**
     * @param $host_id
     * @param $attr
     *
     * @return void
     */
    public function addHost($host_id, $attr = []): void
    {
        $this->hosts[$host_id] = $attr;
    }

    /**
     * @param $host
     * @param $generateConfigurationFile
     * @param Macro[] $hostTemplateMacros
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @return void
     */
    public function processingFromHost(
        &$host,
        $generateConfigurationFile = true,
        array $hostTemplateMacros = [],
        array $hostGroupIds = [],
        array $parentHostIds = [],
    ): void {
        $this->getImages($host);
        $this->getHostTimezone($host);
        $this->getHostTemplates($host, $generateConfigurationFile, $hostTemplateMacros);
        $this->getHostCommands($host);
        $this->getHostPeriods($host);
        $this->getContactGroups($host);
        $this->getContacts($host);
        $this->getHostGroups($host, $hostGroupIds);

        // Set HostCategories
        $hostCategory = HostCategory::getInstance($this->dependencyInjector);
        $this->insertHostInHostCategoryMembers($hostCategory, $host);
        $host['category_tags'] = $hostCategory->getIdsByHostId($host['host_id']);

        $host['parents'] = $this->getParents($parentHostIds);

        $this->manageNotificationInheritance($host, $generateConfigurationFile);

    }

    /**
     * @param $host
     * @param mixed $serviceTemplateMacros
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @return void
     */
    public function generateFromHostId(
        &$host,
        array $hostMacros,
        array $hostTemplateMacros,
        array $serviceMacros,
        $serviceTemplateMacros,
        array $hostGroupIds = [],
        array $parentHostIds = [],
        array $serviceIdsDirect = [],
        array $serviceIdsByHg = [],
    ): void {
        $this->processingFromHost(
            $host,
            hostTemplateMacros: $hostTemplateMacros,
            hostGroupIds: $hostGroupIds,
            parentHostIds: $parentHostIds
        );
        $this->formatMacros($host, $hostMacros);
        $this->getSeverity($host['host_id']);
        $this->getServices($host, $serviceMacros, $serviceTemplateMacros, $serviceIdsDirect);
        $this->getServicesByHg($host, $serviceMacros, $serviceTemplateMacros, $serviceIdsByHg);
        $this->generateObjectInFile($host, $host['host_id']);
        $this->addGeneratedHost($host['host_id']);
    }

    /**
     * @param $pollerId
     * @param $localhost
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @return void
     */
    public function generateFromPollerId($pollerId, $localhost = 0): void
    {
        // ---- Poller-wide host name index (MON-214629) ----
        // Built once upfront, never freed: the only host data needed once a
        // host's own batch is done (getParents() across batches, and the tail
        // steps Dependency/Escalation/generateIndexData, all run after every
        // batch) is a host's name. Keeping just id=>name resident (instead of
        // every host's full row) is what lets the per-batch data below be
        // freed between batches without losing that lookup.
        if (is_null($this->hostNamesById)) {
            $this->hostNamesById = $this->getHostNames($pollerId);
            foreach ($this->hostNamesById as $hostId => $hostName) {
                $this->hosts_by_name[$hostName] = $hostId;
            }
        }
        $allHostIds = array_keys($this->hostNamesById);

        Service::getInstance($this->dependencyInjector)->set_poller($pollerId);

        /** @var ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository */
        $readMonitoringServerRepository = $this->kernel->getContainer()->get(ReadMonitoringServerRepositoryInterface::class);
        $isPollerEncryptionReady = $readMonitoringServerRepository->isEncryptionReady($pollerId);

        /** @var ReadHostRepositoryInterface $readHostRepository */
        $readHostRepository = $this->kernel->getContainer()->get(ReadHostRepositoryInterface::class);
        /** @var ReadHostMacroRepositoryInterface $readHostMacroRepository */
        $readHostMacroRepository = $this->kernel->getContainer()->get(ReadHostMacroRepositoryInterface::class);
        /** @var ReadServiceRepositoryInterface $readServiceRepository */
        $readServiceRepository = $this->kernel->getContainer()->get(ReadServiceRepositoryInterface::class);
        /** @var ReadServiceMacroRepositoryInterface $readServiceMacroRepository */
        $readServiceMacroRepository = $this->kernel->getContainer()->get(ReadServiceMacroRepositoryInterface::class);

        foreach (array_chunk($allHostIds, $this->getHostBatchSize()) as $batchHostIds) {
            $this->generateHostsBatch(
                $batchHostIds,
                $readHostRepository,
                $readHostMacroRepository,
                $readServiceRepository,
                $readServiceMacroRepository,
                $isPollerEncryptionReady
            );
        }

        if ($localhost == 1) {
            MetaService::getInstance($this->dependencyInjector)->generateObjects();
        }

        Hostgroup::getInstance($this->dependencyInjector)->generateObjects();
        Servicegroup::getInstance($this->dependencyInjector)->generateObjects();
        Escalation::getInstance($this->dependencyInjector)->generateObjects();
        Dependency::getInstance($this->dependencyInjector)->generateObjects();
        Severity::getInstance($this->dependencyInjector)->generateObjects();
        HostCategory::getInstance($this->dependencyInjector)->generateObjects();
        ServiceCategory::getInstance($this->dependencyInjector)->generateObjects();
    }

    /**
     * Generate one batch of hosts (MON-214629): load their full rows and
     * relations, generate them, then free that data before the next batch.
     * Objects shared across hosts (templates, commands, periods, contacts...)
     * keep their current single-load, unbatched behaviour — only the data
     * that scales with the number of hosts of the poller is batched here.
     *
     * @param int[] $batchHostIds
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @return void
     */
    private function generateHostsBatch(
        array $batchHostIds,
        ReadHostRepositoryInterface $readHostRepository,
        ReadHostMacroRepositoryInterface $readHostMacroRepository,
        ReadServiceRepositoryInterface $readServiceRepository,
        ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        bool $isPollerEncryptionReady,
    ): void {
        $this->getHostsBatch($batchHostIds);
        Service::getInstance($this->dependencyInjector)->prepareForNextHostBatch($batchHostIds);

        // 1. Batch-load host template parents (1 query)
        $allHostParents = $readHostRepository->findParentsByHostIds($batchHostIds);

        // 2. Compute host inheritance lines, collect all host+template IDs
        $hostInheritanceLines = [];
        $allHostAndTemplateIds = [];
        foreach ($batchHostIds as $hostId) {
            $parents = $allHostParents[$hostId] ?? [];
            $inheritanceLine = InheritanceManager::findInheritanceLine($hostId, $parents);
            $hostInheritanceLines[$hostId] = $inheritanceLine;
            $allHostAndTemplateIds[] = $hostId;
            array_push($allHostAndTemplateIds, ...$inheritanceLine);
        }
        $allHostAndTemplateIds = array_values(array_unique($allHostAndTemplateIds));

        // 3. Batch-load all host macros (1 query)
        $hostMacrosByOwnerId = $this->indexMacrosByOwnerId(
            $readHostMacroRepository->findByHostIds($allHostAndTemplateIds)
        );

        // 4. Batch-load service IDs per host (2 queries)
        $serviceIdsByHostDirect = $readServiceRepository->findServiceIdsLinkedToHostIds($batchHostIds);
        $serviceIdsByHostHg = $readServiceRepository->findServiceIdsLinkedToHostsThroughHostGroups($batchHostIds);

        // 4bis. Batch-load hostgroup and monitoring-parent relations (2 queries, MON-214625)
        $hostGroupIdsByHostId = $readHostRepository->findHostGroupIdsByHostIds($batchHostIds);
        $parentHostIdsByHostId = $readHostRepository->findParentHostIdsByHostIds($batchHostIds);

        // 5. Merge and deduplicate service IDs per host, collect all unique service IDs
        $serviceIdsByHost = [];
        $allUniqueServiceIds = [];
        foreach ($batchHostIds as $hostId) {
            $direct = $serviceIdsByHostDirect[$hostId] ?? [];
            $hg = $serviceIdsByHostHg[$hostId] ?? [];
            $merged = array_values(array_unique(array_merge($direct, $hg)));
            $serviceIdsByHost[$hostId] = $merged;
            foreach ($merged as $sid) {
                $allUniqueServiceIds[$sid] = true;
            }
        }
        $allUniqueServiceIds = array_keys($allUniqueServiceIds);

        // 6. Batch-load service template parents (1 query)
        $allServiceParents = $readServiceRepository->findParentsByServiceIds($allUniqueServiceIds);

        // 7. Compute service inheritance lines, collect all service+template IDs
        $serviceInheritanceLines = [];
        $allServiceAndTemplateIds = [];
        foreach ($allUniqueServiceIds as $serviceId) {
            $parents = $allServiceParents[$serviceId] ?? [];
            $inheritanceLine = ServiceInheritance::createInheritanceLine($serviceId, $parents);
            $serviceInheritanceLines[$serviceId] = $inheritanceLine;
            $allServiceAndTemplateIds[] = $serviceId;
            array_push($allServiceAndTemplateIds, ...$inheritanceLine);
        }
        $allServiceAndTemplateIds = array_values(array_unique($allServiceAndTemplateIds));

        // 8. Batch-load all service macros (1 query)
        $serviceMacrosByOwnerId = $allServiceAndTemplateIds !== []
            ? $this->indexMacrosByOwnerId($readServiceMacroRepository->findByServiceIds(...$allServiceAndTemplateIds))
            : [];

        // ---- Per-host loop (uses only pre-loaded data, no additional queries) ----
        foreach ($this->hosts as $host_id => &$host) {
            $host['host_id'] = $host_id;

            [$hostMacros, $hostTemplateMacros] = $this->resolveHostMacrosFromCache(
                $host_id,
                $hostInheritanceLines[$host_id] ?? [],
                $hostMacrosByOwnerId,
                $isPollerEncryptionReady
            );

            [$serviceMacros, $serviceTemplateMacros] = $this->resolveServiceMacrosFromCache(
                $serviceIdsByHost[$host_id] ?? [],
                $serviceInheritanceLines,
                $serviceMacrosByOwnerId,
                $isPollerEncryptionReady
            );

            $this->generateFromHostId(
                $host,
                $hostMacros,
                $hostTemplateMacros,
                $serviceMacros,
                $serviceTemplateMacros,
                $hostGroupIdsByHostId[$host_id] ?? [],
                $parentHostIdsByHostId[$host_id] ?? [],
                $serviceIdsByHostDirect[$host_id] ?? [],
                $serviceIdsByHostHg[$host_id] ?? []
            );
        }

        // Free this batch's full rows and working state: only hostNamesById
        // (built once upfront, never freed) is needed past this point.
        $this->hosts = null;
    }

    /**
     * @param $host_name
     *
     * @return mixed|null
     */
    public function getHostIdByHostName($host_name)
    {
        return $this->hosts_by_name[$host_name] ?? null;
    }

    /**
     * @return array
     */
    public function getGeneratedParentship()
    {
        return $this->generated_parentship;
    }

    /**
     * @param $hostId
     *
     * @return void
     */
    public function addGeneratedHost($hostId): void
    {
        $this->generatedHosts[] = $hostId;
    }

    /**
     * @return array
     */
    public function getGeneratedHosts()
    {
        return $this->generatedHosts;
    }

    /**
     * @param int $hostId
     * @return array
     */
    public function getCgAndContacts(int $hostId): array
    {
        // we pass null because it can be a meta_host with host_register = '2'
        $host = $this->getHostById($hostId, null);

        $this->getContacts($host);
        $this->getContactGroups($host);
        $this->getHostTemplates($host, false);

        $hostTplInstance = HostTemplate::getInstance($this->dependencyInjector);

        $stack = $host['htpl'];
        $loop = [];
        while (($hostTplId = array_shift($stack))) {
            if (isset($loop[$hostTplId])) {
                continue;
            }
            $loop[$hostTplId] = 1;

            $hostTplInstance->addCacheHostTpl($hostTplId);
            if (! is_null($hostTplInstance->hosts[$hostTplId])) {
                $hostTplInstance->getHostTemplates($hostTplInstance->hosts[$hostTplId], false);
                $hostTplInstance->getContactGroups($hostTplInstance->hosts[$hostTplId]);
                $hostTplInstance->getContacts($hostTplInstance->hosts[$hostTplId]);
                $stack = array_merge($hostTplInstance->hosts[$hostTplId]['htpl'], $stack);
            }
        }

        return $this->manageNotificationInheritance($host, false);
    }

    /**
     * @throws Exception
     * @return void
     */
    public function reset(): void
    {
        $this->hosts_by_name = [];
        $this->hosts = null;
        $this->hostNamesById = null;
        $this->generated_parentship = [];
        $this->generatedHosts = [];
        parent::reset();
    }

    /**
     * Warning: is to be run AFTER running formatMacros to not override severity export.
     *
     * @param $host_id_arg
     *
     * @throws PDOException
     * @return void
     */
    protected function getSeverity($host_id_arg)
    {
        $host_id = null;
        $loop = [];

        $severity_instance = Severity::getInstance($this->dependencyInjector);
        $severity_id = $severity_instance->getHostSeverityByHostId($host_id_arg);
        $this->hosts[$host_id_arg]['severity'] = $severity_instance->getHostSeverityById($severity_id);
        if (! is_null($this->hosts[$host_id_arg]['severity'])) {
            $macros = [
                '_CRITICALITY_LEVEL' => $this->hosts[$host_id_arg]['severity']['level'],
                '_CRITICALITY_ID' => $this->hosts[$host_id_arg]['severity']['hc_id'],
                'severity' => $this->hosts[$host_id_arg]['severity']['hc_id'],
            ];

            $this->hosts[$host_id_arg]['macros'] = array_merge(
                $this->hosts[$host_id_arg]['macros'] ?? [],
                $macros
            );
        }

        $hosts_tpl = &HostTemplate::getInstance($this->dependencyInjector)->hosts;
        $stack = $this->hosts[$host_id_arg]['htpl'];
        while ((is_null($severity_id) && (! is_null($stack) && ($host_id = array_shift($stack))))) {
            if (isset($loop[$host_id])) {
                continue;
            }
            $loop[$host_id] = 1;
            if (isset($hosts_tpl[$host_id]['severity_id'])) {
                $severity_id = $hosts_tpl[$host_id]['severity_id'];
                break;
            }
            if (isset($hosts_tpl[$host_id]['severity_id_from_below'])) {
                $severity_id = $hosts_tpl[$host_id]['severity_id_from_below'];
                break;
            }

            $stack2 = $hosts_tpl[$host_id]['htpl'];
            while ((is_null($severity_id) && (! is_null($stack2) && ($host_id2 = array_shift($stack2))))) {
                if (isset($loop[$host_id2])) {
                    continue;
                }
                $loop[$host_id2] = 1;

                if (isset($hosts_tpl[$host_id2]['severity_id'])) {
                    $severity_id = $hosts_tpl[$host_id2]['severity_id'];
                    break;
                }
                $stack2 = array_merge($hosts_tpl[$host_id2]['htpl'] ?? [], $stack2);
            }

            if ($severity_id) {
                $hosts_tpl[$host_id]['severity_id_from_below'] = $severity_id;
            }
        }

        // For applied on services without severity
        $this->hosts[$host_id_arg]['severity_id_for_services'] = $severity_instance->getHostSeverityById($severity_id);
    }

    /**
     * Index macros by owner ID. Each macro is keyed by its position in the original list,
     * so that the original order can be restored when collecting macros of several owners.
     *
     * @param Macro[] $macros
     *
     * @return array<int, array<int, Macro>>
     */
    private function indexMacrosByOwnerId(array $macros): array
    {
        $macrosByOwnerId = [];
        $position = 0;
        foreach ($macros as $macro) {
            $macrosByOwnerId[$macro->getOwnerId()][$position++] = $macro;
        }

        return $macrosByOwnerId;
    }

    /**
     * Collect the macros belonging to the given owners, in their original order.
     *
     * @param array<int, array<int, Macro>> $macrosByOwnerId Macros indexed by owner ID
     * @param int[] $ownerIds
     *
     * @return Macro[]
     */
    private function collectMacrosOfOwners(array $macrosByOwnerId, array $ownerIds): array
    {
        $macros = [];
        foreach ($ownerIds as $ownerId) {
            $macros += $macrosByOwnerId[$ownerId] ?? [];
        }
        ksort($macros);

        return array_values($macros);
    }

    /**
     * Resolve host macros using pre-loaded data.
     *
     * @param int $hostId
     * @param int[] $inheritanceLine Pre-computed inheritance line
     * @param array<int, array<int, Macro>> $hostMacrosByOwnerId All host macros pre-loaded in bulk, indexed by owner ID
     * @param bool $isPollerEncryptionReady
     *
     * @return array{Macro[], Macro[]}
     */
    private function resolveHostMacrosFromCache(
        int $hostId,
        array $inheritanceLine,
        array $hostMacrosByOwnerId,
        bool $isPollerEncryptionReady,
    ): array {
        $existingHostMacros = $this->collectMacrosOfOwners(
            $hostMacrosByOwnerId,
            array_merge([$hostId], $inheritanceLine)
        );

        array_walk(
            $existingHostMacros,
            fn (Macro &$macro, int|string $key, bool $isPollerEncryptionReady) => $macro->setShouldBeEncrypted($isPollerEncryptionReady),
            $isPollerEncryptionReady
        );

        $directMacros = array_values(array_filter(
            $existingHostMacros,
            fn (Macro $macro) => $macro->getOwnerId() === $hostId
        ));

        $allTemplateMacros = array_values(array_filter(
            $existingHostMacros,
            fn (Macro $macro) => $macro->getOwnerId() !== $hostId
        ));

        return [$directMacros, $allTemplateMacros];
    }

    /**
     * Resolve service macros using pre-loaded data.
     *
     * @param int[] $serviceIds Services linked to this host
     * @param array<int, int[]> $serviceInheritanceLines Pre-computed per-service
     * @param array<int, array<int, Macro>> $serviceMacrosByOwnerId All service macros pre-loaded in bulk, indexed by owner ID
     * @param bool $isPollerEncryptionReady
     *
     * @return array{Macro[], Macro[]}
     */
    private function resolveServiceMacrosFromCache(
        array $serviceIds,
        array $serviceInheritanceLines,
        array $serviceMacrosByOwnerId,
        bool $isPollerEncryptionReady,
    ): array {
        $serviceMacros = [];
        $serviceTemplateMacros = [];

        foreach ($serviceIds as $serviceId) {
            $inheritanceLine = $serviceInheritanceLines[$serviceId] ?? [];

            $existingMacros = $this->collectMacrosOfOwners(
                $serviceMacrosByOwnerId,
                array_merge([$serviceId], $inheritanceLine)
            );

            [$directMacros, $indirectMacros] = Macro::resolveInheritance($existingMacros, $inheritanceLine, $serviceId);
            $serviceMacros = array_merge($serviceMacros, array_values($directMacros));

            $allTemplateMacros = array_filter(
                $existingMacros,
                fn (Macro $macro): bool => $macro->getOwnerId() !== $serviceId
            );
            $serviceTemplateMacros = array_merge($serviceTemplateMacros, array_values($allTemplateMacros));
        }

        array_walk(
            $serviceMacros,
            fn (Macro &$macro, int|string $key, bool $isPollerEncryptionReady) => $macro->setShouldBeEncrypted($isPollerEncryptionReady),
            $isPollerEncryptionReady
        );
        array_walk(
            $serviceTemplateMacros,
            fn (Macro &$macro, int|string $key, bool $isPollerEncryptionReady) => $macro->setShouldBeEncrypted($isPollerEncryptionReady),
            $isPollerEncryptionReady
        );

        return [$serviceMacros, $serviceTemplateMacros];
    }

    /**
     * Light upfront pass (MON-214629): host_id => host_name for every host of
     * the poller, in the same order/filter as the per-batch full-row query
     * below. Cheap enough to keep resident for the whole run (see
     * $hostNamesById), unlike the full rows.
     *
     * @param $poller_id
     *
     * @throws PDOException
     * @return array<int, string>
     */
    private function getHostNames($poller_id): array
    {
        // We use host_register = 1 because we don't want _Module_* hosts
        $stmt = $this->backend_instance->db->prepare('SELECT
              host.host_id, host.host_name
            FROM ns_host_relation, host
            WHERE ns_host_relation.nagios_server_id = :server_id
                AND ns_host_relation.host_host_id = host.host_id
                AND host.host_activate = \'1\' AND host.host_register = \'1\'');
        $stmt->bindParam(':server_id', $poller_id, PDO::PARAM_INT);
        $stmt->execute();

        $names = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var array{host_id: int, host_name: string} $row */
            $names[(int) $row['host_id']] = $row['host_name'];
        }

        return $names;
    }

    /**
     * Full rows of one batch of hosts (MON-214629): same columns and filter
     * as before, restricted to this batch's ids instead of the whole poller.
     *
     * @param int[] $hostIds
     *
     * @throws PDOException
     * @return void
     */
    private function getHostsBatch(array $hostIds): void
    {
        if ($hostIds === []) {
            $this->hosts = [];

            return;
        }

        $placeholders = [];
        foreach ($hostIds as $index => $hostId) {
            $placeholders[':host_' . $index] = $hostId;
        }

        $stmt = $this->backend_instance->db->prepare("SELECT
              {$this->attributes_select}
            FROM host
                LEFT JOIN extended_host_information ON extended_host_information.host_host_id = host.host_id
            WHERE host.host_id IN (" . implode(',', array_keys($placeholders)) . ")
                AND host.host_activate = '1' AND host.host_register = '1'");
        foreach ($placeholders as $placeholder => $hostId) {
            $stmt->bindValue($placeholder, $hostId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $this->hosts = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * Number of hosts processed per batch (MON-214629), overridable with
     * CENTREON_CONFIGURATION_GENERATION_HOST_BATCH_SIZE (chart:
     * configurationExport.hostBatchSize). Falls back to the default on an
     * unset, empty or invalid value.
     *
     * @return int
     */
    private function getHostBatchSize(): int
    {
        $value = getenv('CENTREON_CONFIGURATION_GENERATION_HOST_BATCH_SIZE');
        if ($value === false || ! ctype_digit($value) || (int) $value <= 0) {
            return self::DEFAULT_HOST_BATCH_SIZE;
        }

        return (int) $value;
    }

    /**
     * @param $host
     * @param int[] $hostGroupIds Pre-loaded for all hosts of the poller (MON-214625)
     *
     * @return void
     */
    private function getHostGroups(&$host, array $hostGroupIds = []): void
    {
        $host['group_tags'] ??= [];

        if (! isset($host['hg'])) {
            $host['hg'] = $hostGroupIds;
        }

        $hostgroup = Hostgroup::getInstance($this->dependencyInjector);
        foreach ($host['hg'] as $hostGroupId) {
            $host['group_tags'][] = $hostGroupId;
            $hostgroup->addHostInHg($hostGroupId, $host['host_id'], $host['host_name']);
        }
    }

    /**
     * Resolve the monitoring parent host names of a host from a pre-loaded parent id list.
     * Reads the poller-wide name index (MON-214629), not the current batch's
     * rows: a parent can live in a batch other than the host's own.
     *
     * @param int[] $parentHostIds Pre-loaded for all hosts of the poller (MON-214625)
     *
     * @return string[]
     */
    private function getParents(array $parentHostIds = []): array
    {
        $parents = [];
        foreach ($parentHostIds as $parent_id) {
            if (isset($this->hostNamesById[$parent_id])) {
                $parents[] = $this->hostNamesById[$parent_id];
            }
        }

        return $parents;
    }

    /**
     * @param $host
     * @param Macro[] $serviceMacros
     * @param int[] $serviceIds Pre-loaded for all hosts of the poller (MON-214625)
     *
     * @return void
     */
    private function getServices(&$host, array $serviceMacros, array $serviceTemplateMacros, array $serviceIds = []): void
    {
        $host['services_cache'] = $serviceIds;

        $service = Service::getInstance($this->dependencyInjector);
        foreach ($host['services_cache'] as $service_id) {
            $service->generateFromServiceId(
                hostId: $host['host_id'],
                hostName: $host['host_name'],
                serviceId: $service_id,
                serviceMacros: $serviceMacros,
                serviceTemplateMacros: $serviceTemplateMacros
            );
        }
    }

    /**
     * @param $host
     * @param int[] $serviceIdsByHg Pre-loaded for all hosts of the poller (MON-214625)
     *
     * @return int|void
     */
    private function getServicesByHg(&$host, array $serviceMacros, array $serviceTemplateMacros, array $serviceIdsByHg = [])
    {
        if (count($host['hg']) == 0) {
            return 1;
        }
        $host['services_hg_cache'] = $serviceIdsByHg;

        $service = Service::getInstance($this->dependencyInjector);
        foreach ($host['services_hg_cache'] as $service_id) {
            $service->generateFromServiceId(
                hostId: $host['host_id'],
                hostName: $host['host_name'],
                serviceId: $service_id,
                byHg: 1,
                serviceMacros: $serviceMacros,
                serviceTemplateMacros: $serviceTemplateMacros
            );
        }
    }

    /**
     * @param array $host (passing by Reference)
     * @return array
     */
    private function manageCumulativeInheritance(array &$host): array
    {
        $results = ['cg' => [], 'contact' => []];

        $hostsTpl = HostTemplate::getInstance($this->dependencyInjector)->hosts;
        foreach ($host['htpl'] as $hostIdTopLevel) {
            $stack = [$hostIdTopLevel];
            $loop = [];
            if (! isset($hostsTpl[$hostIdTopLevel]['contacts_computed_cache'])) {
                $contacts = [];
                $cg = [];
                while (($hostId = array_shift($stack))) {
                    if (isset($loop[$hostId]) || ! isset($hostsTpl[$hostId])) {
                        continue;
                    }
                    $loop[$hostId] = 1;
                    // if notifications_enabled is disabled. We don't go in branch
                    if (
                        ! is_null($hostsTpl[$hostId]['notifications_enabled'])
                        && (int) $hostsTpl[$hostId]['notifications_enabled'] === 0
                    ) {
                        continue;
                    }

                    if (count($hostsTpl[$hostId]['contact_groups_cache']) > 0) {
                        $cg = array_merge($cg, $hostsTpl[$hostId]['contact_groups_cache']);
                    }
                    if (count($hostsTpl[$hostId]['contacts_cache']) > 0) {
                        $contacts = array_merge($contacts, $hostsTpl[$hostId]['contacts_cache']);
                    }

                    $stack = array_merge($hostsTpl[$hostId]['htpl'], $stack);
                }

                $hostsTpl[$hostIdTopLevel]['contacts_computed_cache'] = array_unique($contacts);
                $hostsTpl[$hostIdTopLevel]['contact_groups_computed_cache'] = array_unique($cg);
            }

            $results['cg'] = array_merge(
                $results['cg'],
                $hostsTpl[$hostIdTopLevel]['contact_groups_computed_cache']
            );
            $results['contact'] = array_merge(
                $results['contact'],
                $hostsTpl[$hostIdTopLevel]['contacts_computed_cache']
            );
        }

        $results['cg'] = array_unique(array_merge($results['cg'], $host['contact_groups_cache']), SORT_NUMERIC);
        $results['contact'] = array_unique(array_merge($results['contact'], $host['contacts_cache']), SORT_NUMERIC);

        return $results;
    }

    /**
     * @param array $host (passing by Reference)
     * @param string $attribute
     * @return array
     */
    private function manageCloseInheritance(array &$host, string $attribute): array
    {
        if (count($host[$attribute . '_cache']) > 0) {
            return $host[$attribute . '_cache'];
        }

        $hostsTpl = HostTemplate::getInstance($this->dependencyInjector)->hosts;
        foreach ($host['htpl'] as $hostIdTopLevel) {
            $stack = [$hostIdTopLevel];
            $loop = [];
            if (! isset($hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache'])) {
                $hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache'] = [];

                while (($hostId = array_shift($stack))) {
                    if (isset($loop[$hostId])) {
                        continue;
                    }
                    $loop[$hostId] = 1;

                    if (
                        ! is_null($hostsTpl[$hostId]['notifications_enabled'])
                        && (int) $hostsTpl[$hostId]['notifications_enabled'] === 0
                    ) {
                        continue;
                    }

                    if (count($hostsTpl[$hostId][$attribute . '_cache']) > 0) {
                        $hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache']
                            = $hostsTpl[$hostId][$attribute . '_cache'];
                        break;
                    }

                    $stack = array_merge($hostsTpl[$hostId]['htpl'], $stack);
                }
            }

            if (count($hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache']) > 0) {
                return $hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache'];
            }
        }

        return [];
    }

    /**
     * @param array $host
     * @param string $attribute
     * @param string $attributeAdditive
     * @return array
     */
    private function manageVerticalInheritance(array &$host, string $attribute, string $attributeAdditive): array
    {
        $results = $host[$attribute . '_cache'];
        if (
            count($results) > 0
            && (is_null($host[$attributeAdditive]) || $host[$attributeAdditive] != 1)
        ) {
            return $results;
        }

        $hostsTpl = HostTemplate::getInstance($this->dependencyInjector)->hosts;
        $hostIdCache = null;
        foreach ($host['htpl'] as $hostIdTopLevel) {
            $computedCache = [];
            if (! isset($hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache'])) {
                $stack = [[$hostIdTopLevel, 1]];
                $loop = [];
                $currentLevelCatch = null;
                while (([$hostId, $level] = array_shift($stack))) {
                    if (! is_null($currentLevelCatch) && $currentLevelCatch >= $level) {
                        break;
                    }
                    if (isset($loop[$hostId])) {
                        continue;
                    }
                    $loop[$hostId] = 1;

                    if (
                        ! is_null($hostsTpl[$hostId]['notifications_enabled'])
                        && (int) $hostsTpl[$hostId]['notifications_enabled'] === 0
                    ) {
                        continue;
                    }

                    if (count($hostsTpl[$hostId][$attribute . '_cache']) > 0) {
                        $computedCache = array_merge($computedCache, $hostsTpl[$hostId][$attribute . '_cache']);
                        $currentLevelCatch = $level;
                        if (
                            is_null($hostsTpl[$hostId][$attributeAdditive])
                            || $hostsTpl[$hostId][$attributeAdditive] != 1
                        ) {
                            break;
                        }
                    }

                    foreach (array_reverse($hostsTpl[$hostId]['htpl']) as $htplId) {
                        array_unshift($stack, [$htplId, $level + 1]);
                    }
                }

                $hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache'] = array_unique($computedCache);
            }

            if (count($hostsTpl[$hostIdTopLevel][$attribute . '_computed_cache']) > 0) {
                $hostIdCache = $hostIdTopLevel;
                break;
            }
        }

        if (! is_null($hostIdCache)) {
            $results = array_unique(
                array_merge($results, $hostsTpl[$hostIdCache][$attribute . '_computed_cache']),
                SORT_NUMERIC
            );
        }

        return $results;
    }

    /**
     * @param array $host
     * @param array $cg
     */
    private function setContactGroups(array &$host, array $cg = []): void
    {
        $cgInstance = Contactgroup::getInstance($this->dependencyInjector);
        $cgResult = '';
        $cgResultAppend = '';
        foreach ($cg as $cgId) {
            $tmp = $cgInstance->generateFromCgId($cgId);
            if (! is_null($tmp)) {
                $cgResult .= $cgResultAppend . $tmp;
                $cgResultAppend = ',';
            }
        }
        if ($cgResult != '') {
            $host['contact_groups'] = $cgResult;
        }
    }

    /**
     * @param array $host
     * @param array $contacts
     */
    private function setContacts(array &$host, array $contacts = []): void
    {
        $contactInstance = Contact::getInstance($this->dependencyInjector);
        $contactResult = '';
        $contactResultAppend = '';
        foreach ($contacts as $contactId) {
            $tmp = $contactInstance->generateFromContactId($contactId);
            if (! is_null($tmp)) {
                $contactResult .= $contactResultAppend . $tmp;
                $contactResultAppend = ',';
            }
        }
        if ($contactResult != '') {
            $host['contacts'] = $contactResult;
        }
    }

    /**
     * @param array $host
     * @param bool $generate
     * @return array
     */
    private function manageNotificationInheritance(array &$host, bool $generate = true): array
    {
        $results = ['cg' => [], 'contact' => []];

        if (! is_null($host['notifications_enabled']) && (int) $host['notifications_enabled'] === 0) {
            return $results;
        }

        $mode = $this->getInheritanceMode();

        if ($mode === self::CUMULATIVE_NOTIFICATION) {
            $results = $this->manageCumulativeInheritance($host);
        } elseif ($mode === self::CLOSE_NOTIFICATION) {
            $results['cg'] = $this->manageCloseInheritance($host, 'contact_groups');
            $results['contact'] = $this->manageCloseInheritance($host, 'contacts');
        } else {
            $results['cg'] = $this->manageVerticalInheritance($host, 'contact_groups', 'cg_additive_inheritance');
            $results['contact'] = $this->manageVerticalInheritance($host, 'contacts', 'contact_additive_inheritance');
        }

        if ($generate) {
            $this->setContacts($host, $results['contact']);
            $this->setContactGroups($host, $results['cg']);
        }

        return $results;
    }
}
