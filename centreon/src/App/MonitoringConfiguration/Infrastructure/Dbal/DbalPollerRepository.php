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

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CMACertificateCN;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CMACertificateSHA;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCMACertificates;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Exception\PollerAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type RowTypeAlias from DbalGlobalMacroRepository as GlobalMacroRowTypeAlias
 *
 * @phpstan-type RowTypeAlias = array{
 *   poller_id: int,
 *   poller_name: string,
 *   poller_address: string,
 *   is_central: '0'|'1',
 *   is_default: int,
 *   is_activated: '0'|'1',
 *   poller_type: 'vm'|'docker',
 *   poller_uid: int,
 *   gorgone_communication_type: '1'|'2'|'3'|'4',
 *   gorgone_port: int|null,
 *   ssh_port: int|null,
 *   remote_server_use_as_proxy: '0'|'1',
 *   engine_start_command: string|null,
 *   engine_stop_command: string|null,
 *   engine_restart_command: string|null,
 *   engine_reload_command: string|null,
 *   nagios_bin: string|null,
 *   nagiostats_bin: string|null,
 *   nagios_perfdata: string|null,
 *   broker_reload_command: string|null,
 *   centreonbroker_cfg_path: string|null,
 *   centreonbroker_module_path: string|null,
 *   centreonbroker_logs_path: string|null,
 *   centreonconnector_path: string|null,
 *   init_script_centreontrapd: string|null,
 *   snmp_trapd_path_conf: string|null,
 * }
 * @phpstan-type JoinRowTypeAlias = array{
 *   poller_id: int,
 *   poller_name: string,
 *   poller_address: string,
 *   is_central: '0'|'1',
 *   is_default: int,
 *   is_activated: '0'|'1',
 *   poller_type: 'vm'|'docker',
 *   poller_uid: int,
 *   gorgone_communication_type: '1'|'2'|'3'|'4',
 *   gorgone_port: int|null,
 *   ssh_port: int|null,
 *   remote_server_use_as_proxy: '0'|'1',
 *   engine_start_command: string|null,
 *   engine_stop_command: string|null,
 *   engine_restart_command: string|null,
 *   engine_reload_command: string|null,
 *   nagios_bin: string|null,
 *   nagiostats_bin: string|null,
 *   nagios_perfdata: string|null,
 *   broker_reload_command: string|null,
 *   centreonbroker_cfg_path: string|null,
 *   centreonbroker_module_path: string|null,
 *   centreonbroker_logs_path: string|null,
 *   centreonconnector_path: string|null,
 *   init_script_centreontrapd: string|null,
 *   snmp_trapd_path_conf: string|null,
 *   gm_resource_id: int,
 *   gm_resource_name: string,
 *   gm_resource_line: string,
 *   gm_resource_comment: string|null,
 *   gm_resource_activate: '0'|'1',
 *   gm_is_password: 0|1,
 * }
 */
final readonly class DbalPollerRepository extends DbalRepository implements PollerRepository
{
    public const TABLE_NAME = 'nagios_server';
    public const GLOBAL_MACRO_JOIN_TABLE_NAME = 'cfg_resource_instance_relations';

    /**
     * @param TransformerInterface<RowTypeAlias, Poller> $pollerTransformer
     * @param TransformerInterface<GlobalMacroRowTypeAlias, GlobalMacro> $globalMacroTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realTimeConnection,

        #[Autowire(service: DbalPollerTransformer::class)]
        private TransformerInterface $pollerTransformer,

        #[Autowire(service: DbalGlobalMacroTransformer::class)]
        private TransformerInterface $globalMacroTransformer,

        private bool $withCmaCertificates = false,
    ) {
    }

    public function add(Poller $poller): void
    {
        try {
            $qb = $this->connection->createQueryBuilder();

            $qb->insert(self::TABLE_NAME)
                ->values([
                    'name' => ':name',
                    'ns_ip_address' => ':address',
                    'localhost' => ':is_central',
                    'is_default' => ':is_default',
                    'ns_activate' => ':is_activated',
                    'poller_type' => ':poller_type',
                    'uid' => ':uid',
                    'gorgone_communication_type' => ':gorgone_communication_type',
                    'gorgone_port' => ':gorgone_port',
                    'ssh_port' => ':ssh_port',
                    'remote_server_use_as_proxy' => ':remote_server_use_as_proxy',
                    'engine_start_command' => ':engine_start_command',
                    'engine_stop_command' => ':engine_stop_command',
                    'engine_restart_command' => ':engine_restart_command',
                    'engine_reload_command' => ':engine_reload_command',
                    'nagios_bin' => ':nagios_bin',
                    'nagiostats_bin' => ':nagiostats_bin',
                    'nagios_perfdata' => ':nagios_perfdata',
                    'broker_reload_command' => ':broker_reload_command',
                    'centreonbroker_cfg_path' => ':centreonbroker_cfg_path',
                    'centreonbroker_module_path' => ':centreonbroker_module_path',
                    'centreonbroker_logs_path' => ':centreonbroker_logs_path',
                    'centreonconnector_path' => ':centreonconnector_path',
                    'init_script_centreontrapd' => ':init_script_centreontrapd',
                    'snmp_trapd_path_conf' => ':snmp_trapd_path_conf',
                ])
                ->setParameter('name', $poller->name->value)
                ->setParameter('address', $poller->address->value)
                ->setParameter('is_central', $poller->isCentral ? '1' : '0')
                ->setParameter('is_default', $poller->isDefault ? 1 : 0)
                ->setParameter('is_activated', $poller->isActivated ? '1' : '0')
                ->setParameter('poller_type', $poller->pollerType->value)
                ->setParameter('uid', $poller->uid->value)
                ->setParameter('gorgone_communication_type', $this->communicationTypeToDatabase($poller->gorgoneConfiguration->communicationType))
                ->setParameter('gorgone_port', $poller->gorgoneConfiguration->gorgonePort)
                ->setParameter('ssh_port', $poller->gorgoneConfiguration->sshPort)
                ->setParameter('remote_server_use_as_proxy', $poller->gorgoneConfiguration->useRemoteServerAsProxy ? '1' : '0')
                ->setParameter('engine_start_command', $poller->engineConfiguration->startCommand)
                ->setParameter('engine_stop_command', $poller->engineConfiguration->stopCommand)
                ->setParameter('engine_restart_command', $poller->engineConfiguration->restartCommand)
                ->setParameter('engine_reload_command', $poller->engineConfiguration->reloadCommand)
                ->setParameter('nagios_bin', $poller->engineConfiguration->binaryPath)
                ->setParameter('nagiostats_bin', $poller->engineConfiguration->statisticsBinaryPath)
                ->setParameter('nagios_perfdata', $poller->engineConfiguration->perfdataFilePath)
                ->setParameter('broker_reload_command', $poller->brokerConfiguration->reloadCommand)
                ->setParameter('centreonbroker_cfg_path', $poller->brokerConfiguration->configurationPath)
                ->setParameter('centreonbroker_module_path', $poller->brokerConfiguration->modulesPath)
                ->setParameter('centreonbroker_logs_path', $poller->brokerConfiguration->logsPath)
                ->setParameter('centreonconnector_path', $poller->connectorConfiguration->connectorPath)
                ->setParameter('init_script_centreontrapd', $poller->trapConfiguration->initScriptPath)
                ->setParameter('snmp_trapd_path_conf', $poller->trapConfiguration->snmpTrapPathConf)
                ->executeStatement();

            $pollerId = (int) $this->connection->lastInsertId();

            if ($pollerId === 0) {
                throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
            }
            $centralTopologyId = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('platform_topology')
                ->where("type = 'central'")
                ->executeQuery()
                ->fetchOne();

            if (! is_int($centralTopologyId) && ! is_string($centralTopologyId)) {
                throw new \RuntimeException('No central server found in platform_topology');
            }

            $this->connection->createQueryBuilder()
                ->insert('platform_topology')
                ->values([
                    'address' => ':address',
                    'name' => ':name',
                    'type' => ':type',
                    'parent_id' => ':parent_id',
                    'server_id' => ':server_id',
                    'pending' => ':pending',
                ])
                ->setParameter('address', $poller->address->value)
                ->setParameter('name', $poller->name->value)
                ->setParameter('type', 'poller')
                ->setParameter('parent_id', (int) $centralTopologyId, ParameterType::INTEGER)
                ->setParameter('server_id', $pollerId, ParameterType::INTEGER)
                ->setParameter('pending', '0')
                ->executeStatement();
        } catch (UniqueConstraintViolationException $exception) {
            $field = str_contains($exception->getMessage(), 'uniq_uid')
                ? 'uid' : 'name';
            $value = $field === 'uid' ? $poller->uid->value : $poller->name->value;

            throw new PollerAlreadyExistsException([$field => $value], previous: $exception);
        }

        $this->setId($poller, new PollerId($pollerId));
    }

    public function findOneByName(PollerName $name): ?Poller
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'p')
            ->where('p.name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->createPoller($row);
    }

    public function findOneByAddress(PollerAddress $address): ?Poller
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'p')
            ->where('p.ns_ip_address = :address')
            ->setParameter('address', $address->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->createPoller($row);
    }

    public function findAllByGlobalMacro(GlobalMacro $globalMacro): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns(), ...DbalGlobalMacroRepository::getSelectColumns())
            ->from(self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg1')
            ->innerJoin('pg1', self::TABLE_NAME, 'p', 'p.id = pg1.instance_id')
            ->leftJoin('p', self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg2', 'pg2.instance_id = p.id')
            ->leftJoin('pg2', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg2.resource_id') // ensures still filter on relevant pollers
            ->where('pg1.resource_id = :resource_id')
            ->setParameter('resource_id', $globalMacro->id()->value);

        /**
         * @var array<JoinRowTypeAlias> $rows
         */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $pollerRows = [];
        $globalMacroRows = [];

        foreach ($rows as $row) {
            $pollerId = $row['poller_id'];
            $globalMacroId = $row['gm_resource_id'];

            $pollerRows[$pollerId] ??= $row;

            if ($globalMacroId !== null) {
                /** @var GlobalMacroRowTypeAlias $globalMacroRow */
                $globalMacroRow = [
                    'gm_resource_id' => $row['gm_resource_id'],
                    'gm_resource_name' => $row['gm_resource_name'],
                    'gm_resource_line' => $row['gm_resource_line'],
                    'gm_resource_comment' => $row['gm_resource_comment'],
                    'gm_resource_activate' => $row['gm_resource_activate'],
                    'gm_is_password' => $row['gm_is_password'],
                ];
                $globalMacroRows[$pollerId][$globalMacroId] = $globalMacroRow;
            }
        }

        return $this->createPollers($pollerRows, $globalMacroRows);
    }

    public function get(PollerId $pollerId): Poller
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'p')
            ->where('p.id = :poller_id')
            ->setParameter('poller_id', $pollerId->value);

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        if (empty($rows)) {
            throw new PollerNotFoundException(
                ['poller_id' => $pollerId->value],
                sprintf('Poller #%d not found', $pollerId->value)
            );
        }

        $poller = $this->createPoller($rows[0]);
        if ($this->withCmaCertificates) {
            $this->loadCmaCertificates($poller);
        }

        return $poller;
    }

    public function withCmaCertificates(): self
    {
        return new self(
            connection: $this->connection,
            realTimeConnection: $this->realTimeConnection,
            pollerTransformer: $this->pollerTransformer,
            globalMacroTransformer: $this->globalMacroTransformer,
            withCmaCertificates: true,
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'p'): array
    {
        return [
            "{$alias}.id AS poller_id",
            "{$alias}.name AS poller_name",
            "{$alias}.localhost AS is_central",
            "{$alias}.ns_ip_address AS poller_address",
            "{$alias}.is_default AS is_default",
            "{$alias}.ns_activate AS is_activated",
            "{$alias}.poller_type AS poller_type",
            "{$alias}.uid AS poller_uid",
            "{$alias}.gorgone_communication_type AS gorgone_communication_type",
            "{$alias}.gorgone_port AS gorgone_port",
            "{$alias}.ssh_port AS ssh_port",
            "{$alias}.remote_server_use_as_proxy AS remote_server_use_as_proxy",
            "{$alias}.engine_start_command AS engine_start_command",
            "{$alias}.engine_stop_command AS engine_stop_command",
            "{$alias}.engine_restart_command AS engine_restart_command",
            "{$alias}.engine_reload_command AS engine_reload_command",
            "{$alias}.nagios_bin AS nagios_bin",
            "{$alias}.nagiostats_bin AS nagiostats_bin",
            "{$alias}.nagios_perfdata AS nagios_perfdata",
            "{$alias}.broker_reload_command AS broker_reload_command",
            "{$alias}.centreonbroker_cfg_path AS centreonbroker_cfg_path",
            "{$alias}.centreonbroker_module_path AS centreonbroker_module_path",
            "{$alias}.centreonbroker_logs_path AS centreonbroker_logs_path",
            "{$alias}.centreonconnector_path AS centreonconnector_path",
            "{$alias}.init_script_centreontrapd AS init_script_centreontrapd",
            "{$alias}.snmp_trapd_path_conf AS snmp_trapd_path_conf",
        ];
    }

    public function getCentralAddress(): PollerAddress
    {
        $address = $this->connection->createQueryBuilder()
            ->select('address')
            ->from('platform_topology')
            ->where("type = 'central'")
            ->executeQuery()
            ->fetchOne();

        if (! is_string($address)) {
            throw new \RuntimeException('No central server found in platform_topology');
        }

        return new PollerAddress($address);
    }

    private function loadCmaCertificates(Poller $poller): void
    {
        $qb = $this->realTimeConnection->createQueryBuilder();
        $qb->select('i.cma_certificate_sha AS certificate_sha', 'i.cma_certificate_cn  AS certificate_cn')
            ->from('instances', 'i')
            ->where('i.instance_id = :poller_uid')
            ->setParameter('poller_uid', $poller->uid->value);

        $row = $qb->executeQuery()->fetchAssociative() ?: [];
        $certSha = ($row['certificate_sha'] ?? '') !== '' ? $row['certificate_sha'] : null;
        $certCn = ($row['certificate_cn'] ?? '') !== '' ? $row['certificate_cn'] : null;
        $poller->addPollerCMACertificates(
            new PollerCMACertificates(
                certificateSha: is_string($certSha) ? new CMACertificateSHA($certSha) : null,
                certificateCn: is_string($certCn) ? new CMACertificateCN($certCn) : null,
            )
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     * @param array<array<GlobalMacroRowTypeAlias>>|null $globalMacroRowsByPollerId
     *
     * @return Collection<Poller>
     */
    private function createPollers(array $rows, ?array $globalMacroRowsByPollerId = null): Collection
    {
        // fetch all global macros of given pollers
        if ($globalMacroRowsByPollerId !== null && $rows !== []) {
            $globalMacroQb = $this->connection->createQueryBuilder();
            $globalMacroQb->select('p.id AS poller_id', ...DbalGlobalMacroRepository::getSelectColumns())
                ->from(self::TABLE_NAME, 'p')
                ->innerJoin('p', self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg', 'p.id = pg.instance_id')
                ->innerJoin('pg', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg.resource_id')
                ->where($globalMacroQb->expr()->in('p.id', array_map('strval', array_column($rows, 'poller_id'))));

            /**
             * @var array<JoinRowTypeAlias> $globalMacroRows
             */
            $globalMacroRows = $globalMacroQb->executeQuery()->fetchAllAssociative();

            /**
             * @var array<array<GlobalMacroRowTypeAlias>> $globalMacroRowsByPollerId
             */
            $globalMacroRowsByPollerId = [];
            foreach ($globalMacroRows as $globalMacrosRow) {
                $globalMacroRowsByPollerId['poller_id'][] = $globalMacrosRow;
            }
        }

        $pollers = [];
        foreach ($rows as $row) {
            $pollerId = $row['poller_id'];
            $pollers[$pollerId] ??= $this->createPoller(
                $row,
                $globalMacroRowsByPollerId[$pollerId] ?? null,
            );
        }

        return new Collection(array_values($pollers), Poller::class);
    }

    /**
     * @param RowTypeAlias $row
     * @param array<GlobalMacroRowTypeAlias>|null $globalMacroRows
     */
    private function createPoller(array $row, ?array $globalMacroRows = null): Poller
    {
        // fetch all global macros of a given poller
        if ($globalMacroRows === null) {
            $globalMacroQb = $this->connection->createQueryBuilder();
            $globalMacroQb->select(...DbalGlobalMacroRepository::getSelectColumns())
                ->from(self::GLOBAL_MACRO_JOIN_TABLE_NAME, 'pg')
                ->innerJoin('pg', DbalGlobalMacroRepository::TABLE_NAME, 'gm', 'gm.resource_id = pg.resource_id')
                ->where('pg.instance_id = :poller_id')
                ->setParameter('poller_id', $row['poller_id']);

            /**
             * @var array<GlobalMacroRowTypeAlias> $globalMacroRows
             */
            $globalMacroRows = $globalMacroQb->executeQuery()->fetchAllAssociative();
        }

        $poller = $this->pollerTransformer->transform($row);

        $this->hydrateToManyRelation(
            primaryEntity: $poller,
            relatedRows: $globalMacroRows,
            relatedIdKey: 'gm_resource_id',
            relatedFactoryCallback: $this->globalMacroTransformer->transform(...),
            relationCallback: static function (Poller $poller, GlobalMacro $globalMacro): void {
                $poller->addGlobalMacro($globalMacro);
            },
        );

        return $poller;
    }

    private function communicationTypeToDatabase(GorgoneCommunicationTypeEnum $communicationType): string
    {
        return match ($communicationType) {
            GorgoneCommunicationTypeEnum::ZMQ => '1',
            GorgoneCommunicationTypeEnum::SSH => '2',
            GorgoneCommunicationTypeEnum::Pull => '3',
            GorgoneCommunicationTypeEnum::PullWss => '4',
        };
    }
}
