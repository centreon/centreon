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

use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\SortDirectionEnum;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type RowTypeAlias from ServiceTransformer
 * @phpstan-import-type MacroRowTypeAlias from ServiceTransformer
 * @phpstan-import-type ServiceNotificationColumnsTypeAlias from ServiceNotificationColumnsTransformer
 *
 * @phpstan-type OwnRowTypeAlias array{
 *   service_id: int|string,
 *   service_description: string,
 *   service_activate: string,
 *   template_id: int|string|null,
 *   icon_id: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 *   host_activate: string|null,
 *   host_poller_id: int|string|null,
 * }
 * @phpstan-type FormRowTypeAlias array{
 *   check_command_id: int|string|null,
 *   check_command_args: string|null,
 *   check_timeperiod_id: int|string|null,
 *   max_check_attempts: int|string|null,
 *   active_check_enabled: string|null,
 *   passive_check_enabled: string|null,
 *   is_volatile: string|null,
 *   acknowledgement_timeout: int|string|null,
 *   check_freshness: string|null,
 *   freshness_threshold: int|string|null,
 *   flap_detection_enabled: string|null,
 *   low_flap_threshold: int|string|null,
 *   high_flap_threshold: int|string|null,
 *   event_handler_enabled: string|null,
 *   event_handler_command_id: int|string|null,
 *   event_handler_args: string|null,
 *   obsess_over_service: string|null,
 *   retain_status_information: string|null,
 *   retain_non_status_information: string|null,
 *   stalking_options: string|null,
 *   notifications_enabled: string|null,
 *   notification_options: string|null,
 *   notification_interval: int|string|null,
 *   notification_period_id: int|string|null,
 *   first_notification_delay: int|string|null,
 *   recovery_notification_delay: int|string|null,
 *   contact_additive_inheritance: int|string|null,
 *   cg_additive_inheritance: int|string|null,
 *   use_only_contacts_from_host: string|null,
 *   comment: string|null,
 *   geo_coords: string|null,
 *   note: string|null,
 *   note_url: string|null,
 *   action_url: string|null,
 *   icon_id: int|string|null,
 *   alt_icon: string|null,
 *   graph_template_id: int|string|null,
 *   severity_id: int|string|null,
 * }
 * @phpstan-type RelationIdsTypeAlias array{
 *   notification_contact_ids: list<int>,
 *   notification_contact_group_ids: list<int>,
 *   service_group_ids: list<int>,
 *   category_ids: list<int>,
 *   trap_ids: list<int>,
 * }
 * @phpstan-type ListedRowTypeAlias array{
 *   service_id: int|string,
 *   service_description: string,
 *   service_activate: string,
 *   template_id: int|string|null,
 *   icon_id: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 *   host_id: int|string,
 *   host_activate: string|null,
 *   host_poller_id: int|string|null,
 * }
 */
final readonly class DbalServiceRepository extends DbalRepository implements ServiceRepository
{
    use RemovesEmptiedDependenciesTrait;
    use DbalCriteriaApplierTrait;
    use TriStateColumnTrait;
    private const TABLE_NAME = 'service';

    /**
     * @var array<string, string> service column => named parameter, every form column of the `service` row
     */
    private const SERVICE_COLUMN_PARAMETERS = [
        'service_description' => ':name',
        'service_activate' => ':activated',
        'service_template_model_stm_id' => ':templateId',
        'command_command_id' => ':checkCommandId',
        'command_command_id_arg' => ':checkCommandArgs',
        'timeperiod_tp_id' => ':checkTimeperiodId',
        'service_max_check_attempts' => ':maxCheckAttempts',
        'service_normal_check_interval' => ':normalCheckInterval',
        'service_retry_check_interval' => ':retryCheckInterval',
        'service_active_checks_enabled' => ':activeCheckEnabled',
        'service_passive_checks_enabled' => ':passiveCheckEnabled',
        'service_is_volatile' => ':isVolatile',
        'service_notifications_enabled' => ':notificationsEnabled',
        'service_notification_options' => ':notificationOptions',
        'service_notification_interval' => ':notificationInterval',
        'timeperiod_tp_id2' => ':notificationPeriodId',
        'service_first_notification_delay' => ':firstNotificationDelay',
        'service_recovery_notification_delay' => ':recoveryNotificationDelay',
        'contact_additive_inheritance' => ':contactAdditiveInheritance',
        'cg_additive_inheritance' => ':contactGroupAdditiveInheritance',
        'service_use_only_contacts_from_host' => ':useOnlyContactsFromHost',
        'service_check_freshness' => ':checkFreshness',
        'service_freshness_threshold' => ':freshnessThreshold',
        'service_flap_detection_enabled' => ':flapDetectionEnabled',
        'service_low_flap_threshold' => ':lowFlapThreshold',
        'service_high_flap_threshold' => ':highFlapThreshold',
        'service_event_handler_enabled' => ':eventHandlerEnabled',
        'command_command_id2' => ':eventHandlerCommandId',
        'command_command_id_arg2' => ':eventHandlerArgs',
        'service_obsess_over_service' => ':obsessOverService',
        'service_retain_status_information' => ':retainStatusInformation',
        'service_retain_nonstatus_information' => ':retainNonStatusInformation',
        'service_acknowledgement_timeout' => ':acknowledgementTimeout',
        'service_stalking_options' => ':stalkingOptions',
        'service_comment' => ':comment',
        'geo_coords' => ':geoCoords',
    ];

    /** The service's own values, its own icon (as stored, whatever its folder) included. */
    private const OWN_COLUMNS = [
        's.service_id',
        's.service_description',
        's.service_activate',
        's.service_template_model_stm_id AS template_id',
        's.service_normal_check_interval AS normal_check_interval',
        's.service_retry_check_interval AS retry_check_interval',
        // The icon of the service's first extended information row, the row a Get reads too.
        '(SELECT esi.esi_icon_image FROM extended_service_information esi WHERE esi.service_service_id = s.service_id ORDER BY esi.esi_id LIMIT 1) AS icon_id',
    ];

    /**
     * Host state read with the service, so the poller to flag survives the service row deletion.
     */
    private const HOST_STATE_COLUMNS = [
        'h.host_activate',
        '(SELECT MIN(nsr.nagios_server_id) FROM ns_host_relation nsr WHERE nsr.host_host_id = h.host_id) AS host_poller_id',
    ];

    /**
     * @param TransformerInterface<RowTypeAlias, Service> $transformer
     * @param TransformerInterface<?ServiceNotifications, ServiceNotificationColumnsTypeAlias> $notificationColumnsTransformer
     * @param TransformerInterface<list<ServiceStalkingOptionEnum>, ?string> $stalkingOptionsColumnTransformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: ServiceTransformer::class)]
        private TransformerInterface $transformer,
        #[Autowire(service: ServiceNotificationColumnsTransformer::class)]
        private TransformerInterface $notificationColumnsTransformer,
        #[Autowire(service: ServiceStalkingOptionsColumnTransformer::class)]
        private TransformerInterface $stalkingOptionsColumnTransformer,
        private AccessScopeResolver $accessScopes,
        // The real-time ACL cache is read across databases, from the configuration connection.
        #[Autowire(env: 'dbcstg')]
        private string $realTimeDatabaseName,
    ) {
        Assert::stringNotEmpty($realTimeDatabaseName);
    }

    public function add(Service $service): void
    {
        // Before any row of the service is written, see lockPoller().
        if ($service->hostPollerId instanceof PollerId) {
            $this->lockPoller($service->hostPollerId->value);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->insert(self::TABLE_NAME)
            ->values([...self::SERVICE_COLUMN_PARAMETERS, 'service_register' => "'1'"]);
        $this->bindServiceParameters($qb, $service);
        $qb->executeStatement();

        $serviceId = (int) $this->connection->lastInsertId();
        if ($serviceId === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($service, new ServiceId($serviceId));
        $hostId = $service->hostId->value;

        $this->connection->createQueryBuilder()
            ->insert('host_service_relation')
            ->values(['host_host_id' => ':hostId', 'service_service_id' => ':serviceId'])
            ->setParameter('hostId', $hostId, ParameterType::INTEGER)
            ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
            ->executeStatement();

        // Every service row has exactly one companion row here, even when its extended information was left
        // empty: legacy always inserts it, and the readers take the one with the lowest id.
        $extendedInformations = $service->extendedInformations;
        $this->connection->createQueryBuilder()
            ->insert('extended_service_information')
            ->values([
                'service_service_id' => ':serviceId',
                'esi_notes' => ':note',
                'esi_notes_url' => ':noteUrl',
                'esi_action_url' => ':actionUrl',
                'esi_icon_image' => ':iconId',
                'esi_icon_image_alt' => ':altIcon',
                'graph_id' => ':graphTemplateId',
            ])
            ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
            ->setParameter('note', $extendedInformations?->note)
            ->setParameter('noteUrl', $extendedInformations?->noteUrl)
            ->setParameter('actionUrl', $extendedInformations?->actionUrl)
            ->setParameter('iconId', $extendedInformations?->iconId?->value, ParameterType::INTEGER)
            ->setParameter('altIcon', $extendedInformations?->altIcon)
            ->setParameter('graphTemplateId', $service->graphTemplateId?->value, ParameterType::INTEGER)
            ->executeStatement();

        $this->insertRelations($serviceId, $hostId, $service);

        foreach ($service->checkOptions->macros as $macro) {
            $this->insertMacro($serviceId, $macro);
        }
    }

    /**
     * No unique index backs `service_description` on a host: legacy has the same gap, this check is the only
     * safeguard against a duplicate name.
     */
    public function isNameUsedOnHost(ServiceName $name, HostId $hostId, ?ServiceId $excludingServiceId = null): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            // Compared under the column collation, as legacy: case-insensitive, trailing spaces ignored.
            ->andWhere($qb->expr()->eq('s.service_description', $qb->createNamedParameter($name->value)))
            ->setMaxResults(1);

        if ($excludingServiceId instanceof ServiceId) {
            $qb->andWhere($qb->expr()->neq('s.service_id', $qb->createNamedParameter($excludingServiceId->value, ParameterType::INTEGER)));
        }

        return $qb->executeQuery()->fetchOne() !== false;
    }

    /**
     * One query selects, filters, sorts and pages the (host, service) pairs, whatever the number of services, plus
     * one to count them.
     */
    public function findAll(ServiceCriteria $criteria): \IteratorAggregate&\Countable
    {
        $scope = $criteria->accessScope($this->accessScopes->serviceAccessGroups(...), AccessGroupId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, Service::class);
        }

        $qb = $this->createPairsQuery($scope);
        $this->filter($qb, $criteria);

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            $this->selectAndSort($qb, $criteria);

            return new Collection($this->createListedServices($qb), Service::class);
        }

        // A pair may come from several link rows: it counts once.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id, s.service_id)');
        $this->selectAndSort($qb, $criteria);
        $this->applyPagination($qb, $pagination);

        return new InMemoryPaginator(
            items: new Collection($this->createListedServices($qb), Service::class),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...[...self::OWN_COLUMNS, ...self::HOST_STATE_COLUMNS])
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->innerJoin('hsr', 'host', 'h', 'h.host_id = hsr.host_host_id')
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            ->andWhere("h.host_register = '1'");
        $this->keepOnlyServicesWithASingleRelation($qb);

        /** @var list<OwnRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->hydrate($hostId, $rows);
    }

    public function findFromServiceTemplates(HostId $hostId, array $serviceTemplateIds): Collection
    {
        if ($serviceTemplateIds === []) {
            return new Collection([], Service::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...[...self::OWN_COLUMNS, ...self::HOST_STATE_COLUMNS])
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->innerJoin('hsr', 'host', 'h', 'h.host_id = hsr.host_host_id')
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            ->andWhere("s.service_register = '1'")
            ->andWhere($qb->expr()->in('s.service_template_model_stm_id', $qb->createNamedParameter($serviceTemplateIds, ArrayParameterType::INTEGER)));
        $this->keepOnlyServicesWithASingleRelation($qb);

        /** @var list<OwnRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->hydrate($hostId, $rows);
    }

    /**
     * Same pairs as {@see findAll()}; the pair with the lowest host id is read, then the whole form of the service
     * on that host, then its macros.
     */
    public function findOne(ServiceId $id, ActingUser $actor): ?Service
    {
        $scope = $this->accessScopes->serviceAccessGroups($actor);
        if ($scope->allowsNothing()) {
            return null;
        }

        $qb = $this->createPairsQuery($scope);
        $qb->select(...[...self::OWN_COLUMNS, 'h.host_id', ...self::HOST_STATE_COLUMNS])
            ->andWhere($qb->expr()->eq('s.service_id', $qb->createNamedParameter($id->value, ParameterType::INTEGER)))
            ->orderBy('h.host_id', 'ASC')
            ->setMaxResults(1);

        /** @var ListedRowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            return null;
        }

        $formRow = $this->findFormRow($id->value);
        if ($formRow === null) {
            // Deleted between the two reads: not found, like a service that never existed.
            return null;
        }

        return $this->transformer->transform([
            ...$row,
            ...$formRow,
            ...$this->findRelationIds($id->value, (int) $row['host_id']),
            'macros' => $this->findMacroRowsByServiceIds([$id->value])[$id->value] ?? [],
        ]);
    }

    public function updateActivationStatus(ServiceId $id, bool $activated): void
    {
        // service_register = '1' mirrors findOne so a template is never toggled; contract on the interface.
        $this->connection->createQueryBuilder()
            ->update(self::TABLE_NAME)
            ->set('service_activate', ':activated')
            ->where('service_id = :id')
            ->andWhere("service_register = '1'")
            ->setParameter('activated', $activated ? '1' : '0')
            ->setParameter('id', $id->value, ParameterType::INTEGER)
            ->executeStatement();
    }

    public function remove(Service $service): void
    {
        $serviceId = $service->id()->value;
        $parentDependencyIds = $this->findDependencyIds('dependency_serviceParent_relation', 'service_service_id', $serviceId);

        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->where($qb->expr()->eq('service_id', $qb->createNamedParameter($serviceId, ParameterType::INTEGER)))
            ->andWhere("service_register = '1'")
            ->executeStatement();

        $this->deleteDependenciesWithoutMember('dependency_serviceParent_relation', $parentDependencyIds);
    }

    /**
     * Takes the exclusive lock of the poller row of the service's host, before any row of the service is written.
     *
     * Writing a service takes a shared lock on its host row, through the foreign keys of `host_service_relation`
     * and `servicegroup_relation`. The host's poller is flagged for a configuration reload later in the same
     * transaction, which needs the poller row exclusively. A concurrent write of that host locks the poller row
     * first, then the host row ({@see DbalHostRepository}): without this lock, each would hold what the other
     * waits for, a deadlock. Locking the poller first makes the second write wait for the first instead.
     */
    private function lockPoller(int $pollerId): void
    {
        $this->connection->executeQuery(
            'SELECT id FROM nagios_server WHERE id = :poller_id FOR UPDATE',
            ['poller_id' => $pollerId],
            ['poller_id' => ParameterType::INTEGER],
        );
    }

    /**
     * The relations of a service, all keyed on its id: its contacts and contact groups, its service groups on its
     * host, its categories and its severity (one table, told apart by `service_categories.level`), its traps.
     */
    private function insertRelations(int $serviceId, int $hostId, Service $service): void
    {
        $notifications = $service->notifications;
        if ($notifications instanceof ServiceNotifications) {
            foreach ($notifications->contactIds as $contactId) {
                $this->connection->createQueryBuilder()
                    ->insert('contact_service_relation')
                    ->values(['contact_id' => ':contactId', 'service_service_id' => ':serviceId'])
                    ->setParameter('contactId', $contactId->value, ParameterType::INTEGER)
                    ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
                    ->executeStatement();
            }

            foreach ($notifications->contactGroupIds as $contactGroupId) {
                $this->connection->createQueryBuilder()
                    ->insert('contactgroup_service_relation')
                    ->values(['contactgroup_cg_id' => ':contactGroupId', 'service_service_id' => ':serviceId'])
                    ->setParameter('contactGroupId', $contactGroupId->value, ParameterType::INTEGER)
                    ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
                    ->executeStatement();
            }
        }

        foreach ($service->serviceGroupIds as $serviceGroupId) {
            $this->connection->createQueryBuilder()
                ->insert('servicegroup_relation')
                ->values(['host_host_id' => ':hostId', 'service_service_id' => ':serviceId', 'servicegroup_sg_id' => ':serviceGroupId'])
                ->setParameter('hostId', $hostId, ParameterType::INTEGER)
                ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
                ->setParameter('serviceGroupId', $serviceGroupId->value, ParameterType::INTEGER)
                ->executeStatement();
        }

        foreach ($service->categoryIds as $categoryId) {
            $this->linkToServiceCategory($serviceId, $categoryId->value);
        }

        if ($service->severityId instanceof ServiceSeverityId) {
            $this->linkToServiceCategory($serviceId, $service->severityId->value);
        }

        foreach ($service->trapIds as $trapId) {
            $this->connection->createQueryBuilder()
                ->insert('traps_service_relation')
                ->values(['service_id' => ':serviceId', 'traps_id' => ':trapId'])
                ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
                ->setParameter('trapId', $trapId->value, ParameterType::INTEGER)
                ->executeStatement();
        }
    }

    private function linkToServiceCategory(int $serviceId, int $categoryId): void
    {
        $this->connection->createQueryBuilder()
            ->insert('service_categories_relation')
            ->values(['sc_id' => ':categoryId', 'service_service_id' => ':serviceId'])
            ->setParameter('categoryId', $categoryId, ParameterType::INTEGER)
            ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
            ->executeStatement();
    }

    private function insertMacro(int $serviceId, ServiceMacro $macro): void
    {
        $this->connection->createQueryBuilder()
            ->insert('on_demand_macro_service')
            ->values([
                'svc_macro_name' => ':macroName',
                'svc_macro_value' => ':macroValue',
                'is_password' => ':isPassword',
                'svc_svc_id' => ':serviceId',
            ])
            ->setParameter('macroName', $macro->name->toStorageName())
            ->setParameter('macroValue', $macro->value)
            // Legacy stores 1 for a password macro and NULL otherwise, never 0.
            ->setParameter('isPassword', $macro->isPassword ? 1 : null, ParameterType::INTEGER)
            ->setParameter('serviceId', $serviceId, ParameterType::INTEGER)
            ->executeStatement();
    }

    /**
     * Binds every parameter of {@see self::SERVICE_COLUMN_PARAMETERS} in the legacy storage formats: tri-states
     * as '0', '1', '2', arguments encoded; the notification settings and the stalking options through their
     * transformers, which own those formats.
     */
    private function bindServiceParameters(QueryBuilder $qb, Service $service): void
    {
        $scheduling = $service->schedulingOptions;
        $dataProcessing = $service->dataProcessing;
        $extendedInformations = $service->extendedInformations;
        $notificationColumns = $this->notificationColumnsTransformer->transform($service->notifications);

        $qb
            ->setParameter('name', $service->name->value)
            ->setParameter('activated', $service->activated ? '1' : '0')
            ->setParameter('templateId', $service->templateId?->value, ParameterType::INTEGER)
            ->setParameter('checkCommandId', $service->checkOptions->checkCommandId?->value, ParameterType::INTEGER)
            ->setParameter('checkCommandArgs', CommandArgumentsFormatter::format($service->checkOptions->args))
            ->setParameter('checkTimeperiodId', $scheduling->checkTimeperiodId?->value, ParameterType::INTEGER)
            ->setParameter('maxCheckAttempts', $scheduling->maxCheckAttempts, ParameterType::INTEGER)
            ->setParameter('normalCheckInterval', $scheduling->normalCheckInterval, ParameterType::INTEGER)
            ->setParameter('retryCheckInterval', $scheduling->retryCheckInterval, ParameterType::INTEGER)
            ->setParameter('activeCheckEnabled', $this->triStateToColumn($scheduling->activeCheckEnabled))
            ->setParameter('passiveCheckEnabled', $this->triStateToColumn($scheduling->passiveCheckEnabled))
            ->setParameter('isVolatile', $this->triStateToColumn($scheduling->isVolatile))
            ->setParameter('notificationsEnabled', $notificationColumns['notificationsEnabled'])
            ->setParameter('notificationOptions', $notificationColumns['notificationOptions'])
            ->setParameter('notificationInterval', $notificationColumns['notificationInterval'], ParameterType::INTEGER)
            ->setParameter('notificationPeriodId', $notificationColumns['notificationPeriodId'], ParameterType::INTEGER)
            ->setParameter('firstNotificationDelay', $notificationColumns['firstNotificationDelay'], ParameterType::INTEGER)
            ->setParameter('recoveryNotificationDelay', $notificationColumns['recoveryNotificationDelay'], ParameterType::INTEGER)
            ->setParameter('contactAdditiveInheritance', $notificationColumns['contactAdditiveInheritance'], ParameterType::BOOLEAN)
            ->setParameter('contactGroupAdditiveInheritance', $notificationColumns['contactGroupAdditiveInheritance'], ParameterType::BOOLEAN)
            ->setParameter('useOnlyContactsFromHost', $notificationColumns['useOnlyContactsFromHost'])
            ->setParameter('checkFreshness', $this->triStateToColumn($dataProcessing->checkFreshness))
            ->setParameter('freshnessThreshold', $dataProcessing->freshnessThreshold, ParameterType::INTEGER)
            ->setParameter('flapDetectionEnabled', $this->triStateToColumn($dataProcessing->flapDetectionEnabled))
            ->setParameter('lowFlapThreshold', $dataProcessing->lowFlapThreshold, ParameterType::INTEGER)
            ->setParameter('highFlapThreshold', $dataProcessing->highFlapThreshold, ParameterType::INTEGER)
            ->setParameter('eventHandlerEnabled', $this->triStateToColumn($dataProcessing->eventHandlerEnabled))
            ->setParameter('eventHandlerCommandId', $dataProcessing->eventHandlerCommandId?->value, ParameterType::INTEGER)
            ->setParameter('eventHandlerArgs', CommandArgumentsFormatter::format($dataProcessing->eventHandlerArgs))
            ->setParameter('obsessOverService', $this->triStateToColumn($dataProcessing->obsessOverService))
            ->setParameter('retainStatusInformation', $this->triStateToColumn($dataProcessing->retainStatusInformation))
            ->setParameter('retainNonStatusInformation', $this->triStateToColumn($dataProcessing->retainNonStatusInformation))
            ->setParameter('acknowledgementTimeout', $dataProcessing->acknowledgmentTimeout, ParameterType::INTEGER)
            ->setParameter('stalkingOptions', $this->stalkingOptionsColumnTransformer->transform($dataProcessing->stalkingOptions))
            ->setParameter('comment', $extendedInformations?->comment)
            ->setParameter('geoCoords', $extendedInformations?->geoCoordinates instanceof GeoCoordinates ? (string) $extendedInformations->geoCoordinates : null);
    }

    /**
     * Form columns and relations of the service on this host; the severity is not ACL-filtered here.
     *
     * @return ?FormRowTypeAlias null when the service no longer exists
     */
    private function findFormRow(int $serviceId): ?array
    {
        $qb = $this->connection->createQueryBuilder();

        $severity = '(SELECT MIN(scr.sc_id)
            FROM service_categories_relation scr
            INNER JOIN service_categories sc ON sc.sc_id = scr.sc_id
            WHERE scr.service_service_id = s.service_id AND sc.level IS NOT NULL)';

        $qb->select(
            's.command_command_id AS check_command_id',
            's.command_command_id_arg AS check_command_args',
            's.timeperiod_tp_id AS check_timeperiod_id',
            's.service_max_check_attempts AS max_check_attempts',
            's.service_active_checks_enabled AS active_check_enabled',
            's.service_passive_checks_enabled AS passive_check_enabled',
            's.service_is_volatile AS is_volatile',
            's.service_acknowledgement_timeout AS acknowledgement_timeout',
            's.service_check_freshness AS check_freshness',
            's.service_freshness_threshold AS freshness_threshold',
            's.service_flap_detection_enabled AS flap_detection_enabled',
            's.service_low_flap_threshold AS low_flap_threshold',
            's.service_high_flap_threshold AS high_flap_threshold',
            's.service_event_handler_enabled AS event_handler_enabled',
            's.command_command_id2 AS event_handler_command_id',
            's.command_command_id_arg2 AS event_handler_args',
            's.service_obsess_over_service AS obsess_over_service',
            's.service_retain_status_information AS retain_status_information',
            's.service_retain_nonstatus_information AS retain_non_status_information',
            's.service_stalking_options AS stalking_options',
            's.service_notifications_enabled AS notifications_enabled',
            's.service_notification_options AS notification_options',
            's.service_notification_interval AS notification_interval',
            's.timeperiod_tp_id2 AS notification_period_id',
            's.service_first_notification_delay AS first_notification_delay',
            's.service_recovery_notification_delay AS recovery_notification_delay',
            's.contact_additive_inheritance',
            's.cg_additive_inheritance',
            's.service_use_only_contacts_from_host AS use_only_contacts_from_host',
            's.service_comment AS comment',
            's.geo_coords',
            'esi.esi_notes AS note',
            'esi.esi_notes_url AS note_url',
            'esi.esi_action_url AS action_url',
            'esi.esi_icon_image AS icon_id',
            'esi.esi_icon_image_alt AS alt_icon',
            'esi.graph_id AS graph_template_id',
            $severity . ' AS severity_id',
        )
            ->from(self::TABLE_NAME, 's')
            ->leftJoin(
                's',
                'extended_service_information',
                'esi',
                'esi.esi_id = (SELECT MIN(esi_first.esi_id) FROM extended_service_information esi_first WHERE esi_first.service_service_id = s.service_id)',
            )
            ->where($qb->expr()->eq('s.service_id', $qb->createNamedParameter($serviceId, ParameterType::INTEGER)));

        /** @var FormRowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        return $row !== false ? $row : null;
    }

    /**
     * @return RelationIdsTypeAlias
     */
    private function findRelationIds(int $serviceId, int $hostId): array
    {
        $contacts = $this->connection->createQueryBuilder();
        $contacts->select('csr.contact_id')
            ->from('contact_service_relation', 'csr')
            ->where($contacts->expr()->eq('csr.service_service_id', $contacts->createNamedParameter($serviceId, ParameterType::INTEGER)));

        $contactGroups = $this->connection->createQueryBuilder();
        $contactGroups->select('cgsr.contactgroup_cg_id')
            ->from('contactgroup_service_relation', 'cgsr')
            ->where($contactGroups->expr()->eq('cgsr.service_service_id', $contactGroups->createNamedParameter($serviceId, ParameterType::INTEGER)));

        // No unique key on relation tables: DISTINCT reads a duplicated link once.
        $serviceGroups = $this->connection->createQueryBuilder();
        $serviceGroups->select('sgr.servicegroup_sg_id')
            ->distinct()
            ->from('servicegroup_relation', 'sgr')
            ->where($serviceGroups->expr()->eq('sgr.service_service_id', $serviceGroups->createNamedParameter($serviceId, ParameterType::INTEGER)))
            ->andWhere($serviceGroups->expr()->eq('sgr.host_host_id', $serviceGroups->createNamedParameter($hostId, ParameterType::INTEGER)))
            ->andWhere('sgr.hostgroup_hg_id IS NULL');

        $categories = $this->connection->createQueryBuilder();
        $categories->select('scr.sc_id')
            ->distinct()
            ->from('service_categories_relation', 'scr')
            ->innerJoin('scr', 'service_categories', 'sc', 'sc.sc_id = scr.sc_id')
            ->where($categories->expr()->eq('scr.service_service_id', $categories->createNamedParameter($serviceId, ParameterType::INTEGER)))
            ->andWhere('sc.level IS NULL');

        $traps = $this->connection->createQueryBuilder();
        $traps->select('tsr.traps_id')
            ->distinct()
            ->from('traps_service_relation', 'tsr')
            ->where($traps->expr()->eq('tsr.service_id', $traps->createNamedParameter($serviceId, ParameterType::INTEGER)));

        return [
            'notification_contact_ids' => $this->fetchIds($contacts),
            'notification_contact_group_ids' => $this->fetchIds($contactGroups),
            'service_group_ids' => $this->fetchIds($serviceGroups),
            'category_ids' => $this->fetchIds($categories),
            'trap_ids' => $this->fetchIds($traps),
        ];
    }

    /**
     * @return list<int>
     */
    private function fetchIds(QueryBuilder $qb): array
    {
        /** @var list<int|string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();

        return array_map(intval(...), $ids);
    }

    /**
     * A listable pair: a real service directly linked to a real host, the service having no host group link at
     * all (those are not handled here, even when also linked to hosts directly), and, for a restricted actor,
     * granted to one of their groups by the real-time ACL cache.
     *
     * @param AccessScope<AccessGroupId> $scope
     */
    private function createPairsQuery(AccessScope $scope): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', 'host', 'h', "h.host_id = hsr.host_host_id AND h.host_register = '1'")
            ->innerJoin('hsr', self::TABLE_NAME, 's', "s.service_id = hsr.service_service_id AND s.service_register = '1'")
            ->where(
                'NOT EXISTS (
                    SELECT 1 FROM host_service_relation hsrg
                    WHERE hsrg.service_service_id = s.service_id AND hsrg.hostgroup_hg_id IS NOT NULL
                )'
            )
            // A nameless service is not a valid state: this only guards against the nullable schema column,
            // so one malformed row cannot fail the whole listing.
            ->andWhere("s.service_description IS NOT NULL AND s.service_description != ''")
            // A link row may be duplicated: one row per pair.
            ->groupBy('h.host_id', 's.service_id');

        if (! $scope->isUnrestricted()) {
            $qb->andWhere(sprintf(
                'EXISTS (
                    SELECT 1 FROM %s.centreon_acl acl
                    WHERE acl.group_id IN (%s) AND acl.host_id = h.host_id AND acl.service_id = s.service_id
                )',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
                $qb->createNamedParameter($scope->allowedIdValues(), ArrayParameterType::INTEGER),
            ));
        }

        return $qb;
    }

    private function filter(QueryBuilder $qb, ServiceCriteria $criteria): void
    {
        if (($hostName = $criteria->getHostName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $hostName . '%')));
        }

        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('s.service_description', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($templateId = $criteria->getTemplateId()) !== null) {
            $qb->andWhere($qb->expr()->eq('s.service_template_model_stm_id', $qb->createNamedParameter($templateId, ParameterType::INTEGER)));
        }

        if (($activated = $criteria->getActivated()) !== null) {
            $qb->andWhere($qb->expr()->eq('s.service_activate', $qb->createNamedParameter($activated ? '1' : '0')));
        }

        if (($hostActivated = $criteria->getHostActivated()) !== null) {
            $qb->andWhere($qb->expr()->eq('h.host_activate', $qb->createNamedParameter($hostActivated ? '1' : '0')));
        }
    }

    /**
     * The client sort replaces the default one (host name, then service name). The host id, then the service
     * id, always come last, so a page never depends on the order the database happens to return ties in.
     */
    private function selectAndSort(QueryBuilder $qb, ServiceCriteria $criteria): void
    {
        $qb->select(...[...self::OWN_COLUMNS, 'h.host_id', ...self::HOST_STATE_COLUMNS]);

        $mapping = $criteria->getFieldMapping();
        $sorted = false;
        foreach ($criteria->getSort() as $field => $direction) {
            if (isset($mapping[$field])) {
                $qb->addOrderBy($mapping[$field], $direction === SortDirectionEnum::DESC ? 'DESC' : 'ASC');
                $sorted = true;
            }
        }

        if (! $sorted) {
            $qb->addOrderBy('h.host_name', 'ASC')->addOrderBy('s.service_description', 'ASC');
        }

        $qb->addOrderBy('h.host_id', 'ASC')->addOrderBy('s.service_id', 'ASC');
    }

    /**
     * @return list<Service>
     */
    private function createListedServices(QueryBuilder $qb): array
    {
        /** @var list<ListedRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): Service => $this->transformer->transform([...$row, 'macros' => []]), $rows);
    }

    /**
     * A service reachable through more than one relation row (another host, or a shared hostgroup)
     * is not exclusively one host's, and is left alone — mirrors legacy
     * `findServiceIdsExclusivelyLinkedToHostId()`.
     */
    private function keepOnlyServicesWithASingleRelation(QueryBuilder $qb): void
    {
        $qb->innerJoin(
            's',
            '(SELECT service_service_id FROM host_service_relation GROUP BY service_service_id HAVING COUNT(*) = 1)',
            'uniq',
            'uniq.service_service_id = s.service_id',
        );
    }

    /**
     * @param list<OwnRowTypeAlias> $rows
     *
     * @return Collection<Service>
     */
    private function hydrate(HostId $hostId, array $rows): Collection
    {
        if ($rows === []) {
            return new Collection([], Service::class);
        }

        $serviceIds = array_map(static fn (array $row): int => (int) $row['service_id'], $rows);
        $macrosByServiceId = $this->findMacroRowsByServiceIds($serviceIds);

        $services = array_map(
            fn (array $row): Service => $this->transformer->transform([
                ...$row,
                'host_id' => $hostId->value,
                'macros' => $macrosByServiceId[(int) $row['service_id']] ?? [],
            ]),
            $rows,
        );

        return new Collection($services, Service::class);
    }

    /**
     * @param list<int> $serviceIds
     *
     * @return array<int, list<MacroRowTypeAlias>>
     */
    private function findMacroRowsByServiceIds(array $serviceIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('svc_macro_id AS id', 'svc_svc_id', 'svc_macro_name AS name', 'svc_macro_value AS value', 'is_password', 'description')
            ->from('on_demand_macro_service')
            ->where($qb->expr()->in('svc_svc_id', $qb->createNamedParameter($serviceIds, ArrayParameterType::INTEGER)))
            ->orderBy('macro_order')
            ->addOrderBy('svc_macro_id');

        /** @var list<array{id: int|string, svc_svc_id: int|string, name: string, value: string, is_password: string|int|null, description: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['svc_svc_id']][] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'value' => $row['value'],
                'is_password' => $row['is_password'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
