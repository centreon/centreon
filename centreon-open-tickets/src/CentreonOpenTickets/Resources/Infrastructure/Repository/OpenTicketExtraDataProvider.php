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

namespace CentreonOpenTickets\Resources\Infrastructure\Repository;

use Centreon\Domain\Monitoring\Resource;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use PDOException;

/**
 * @phpstan-type _TicketData array{
 *  resource_id:int,
 *  ticket_id:string,
 *  subject:string,
 *  timestamp: int
 * }
 */
final class OpenTicketExtraDataProvider extends AbstractRepositoryRDB implements ExtraDataProviderInterface
{
    use SqlMultipleBindTrait;

    private const DATA_PROVIDER_SOURCE_NAME = 'open_tickets';

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
    public function getExtraDataSourceName(): string
    {
        return self::DATA_PROVIDER_SOURCE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function supportsExtraData(ResourceFilter $filter): bool
    {
        return $filter->getRuleId() !== null;
    }

    /**
     * @inheritDoc
     */
    public function getSubFilter(ResourceFilter $filter): string
    {

        // Only get subRequest is asked and if ruleId is provided
        if (
            $filter->getOnlyWithTicketsOpened() === false
            || $filter->getRuleId() === null
        ) {
            return '';
        }

        $macroName = $this->getMacroNameFromRuleId($filter->getRuleId());

        if ($macroName === null) {
            throw new \Exception('Macro name used for rule not found');
        }

        return <<<SQL
                AND EXISTS (
                    SELECT 1 FROM `:dbstg`.hosts h
                    LEFT JOIN `:dbstg`.services s
                        ON s.host_id = h.host_id
                    LEFT JOIN `:dbstg`.customvariables host_customvariables
                        ON (
                            h.host_id = host_customvariables.host_id
                            AND (host_customvariables.service_id IS NULL OR host_customvariables.service_id = 0)
                            AND host_customvariables.name = '{$macroName}'
                        )
                    LEFT JOIN `:dbstg`.mod_open_tickets host_tickets
                    ON (
                        host_customvariables.value = host_tickets.ticket_value
                        AND (host_tickets.timestamp > h.last_time_up OR h.last_time_up IS NULL)
                    )
                    LEFT JOIN `:dbstg`.customvariables service_customvariables
                        ON (
                            s.service_id = service_customvariables.service_id
                            AND s.host_id = service_customvariables.host_id
                            AND service_customvariables.name = '{$macroName}'
                        )
                    LEFT JOIN `:dbstg`.mod_open_tickets service_tickets
                        ON (
                            service_customvariables.value = service_tickets.ticket_value
                            AND (service_tickets.timestamp > s.last_time_ok OR s.last_time_ok IS NULL)
                        )
                    WHERE (
                            (h.host_id = resources.parent_id AND s.service_id = resources.id)
                            OR (h.host_id = resources.id AND s.service_id IS NULL)
                        )
                        AND (host_tickets.timestamp IS NOT NULL OR service_tickets.timestamp IS NOT NULL)
                    LIMIT 1
                )
            SQL;
    }

    /**
     * @param Resource[] $resources
     * @return Resource[]
     */
    private function getServiceResources(array $resources): array
    {
        return array_filter(
            $resources,
            static fn (Resource $resource) => $resource->getType() === Resource::TYPE_SERVICE
        );
    }

    /**
     * @param Resource[] $resources
     * @return Resource[]
     */
    private function getHostResources(array $resources): array
    {
        return array_filter(
            $resources,
            static fn (Resource $resource) => $resource->getType() === Resource::TYPE_HOST
        );
    }

    /**
     * @inheritDoc
     */
    public function getExtraDataForResources(ResourceFilter $filter, array $resources): array
    {
        $data = [];

        // Provide information only if rule ID is provided and resources is not EMPTY
        if ($filter->getRuleId() === null || $resources === []) {
            return $data;
        }

        $macroName = $this->getMacroNameFromRuleId($filter->getRuleId());

        if ($macroName === null) {
            throw new \Exception('Macro name used for rule not found');
        }

        $parentResourceIds = [];
        $resourceIds = [];

        // extract resource id for services and linked hosts
        foreach ($this->getServiceResources($resources) as $resource) {
            if (
                $resource->getResourceId() !== null
                && ! in_array($resource->getResourceId(), $parentResourceIds)
            ) {
                $resourceIds[] = $resource->getResourceId();
            }

            if (
                $resource->getParent() !== null
                && $resource->getParent()->getResourceId() !== null
                && ! in_array($resource->getParent()->getResourceId(), $parentResourceIds)
            ) {
                $parentResourceIds[] = $resource->getParent()->getResourceId();
            }
        }

        // extract resource ids for hosts
        foreach ($this->getHostResources($resources) as $resource) {
            if (
                $resource->getResourceId() !== null
                && ! in_array($resource->getResourceId(), $parentResourceIds)
            ) {
                $parentResourceIds[] = $resource->getResourceId();
            }
        }

        // avoid key re-indexing. index = resource_id
        return $this->getResourceTickets($resourceIds, $macroName)
            + $this->getParentResourceTickets($parentResourceIds, $macroName);
    }

    /**
     * @param int[] $resources
     * @param string $macroName
     * @return array<int, array{
     *  id:int,
     *  subject:string,
     *  created_at:\DateTimeInterface
     * }>|array{}

     */
    private function getResourceTickets(array $resources, string $macroName): array
    {
        if ($resources === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery(array_values($resources), ':resource_id');

        $request = <<<SQL
            SELECT
                r.resource_id,
                tickets.ticket_id,
                tickets.timestamp,
                tickets.user,
                tickets_data.subject
            FROM `:dbstg`.resources r
            LEFT JOIN `:dbstg`.services s
                ON s.service_id = r.id
                AND s.host_id = r.parent_id
            LEFT JOIN `:dbstg`.customvariables cv
                ON cv.service_id = s.service_id
                AND cv.host_id = s.host_id
                AND cv.name = :macroName
            LEFT JOIN `:dbstg`.mod_open_tickets tickets
                ON tickets.ticket_value = cv.value
                AND (tickets.timestamp > s.last_time_ok OR s.last_time_ok IS NULL)
            LEFT JOIN `:dbstg`.mod_open_tickets_data tickets_data
                ON tickets_data.ticket_id = tickets.ticket_id
            WHERE r.resource_id IN ({$bindQuery})
                AND tickets.timestamp IS NOT NULL;
        SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':macroName', $macroName, \PDO::PARAM_STR);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $tickets = [];

        while (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var _TicketData $record
             */
            $tickets[(int) $record['resource_id']] = [
                'id' => (int) $record['ticket_id'],
                'subject' => $record['subject'],
                'created_at' => (new \DateTimeImmutable())->setTimestamp((int) $record['timestamp'])
            ];
        }

        return $tickets;
    }

    /**
     * @param int[] $parentResources
     * @param string $macroName
     * @return array<int, array{
     *  id:int,
     *  subject:string,
     *  created_at:\DateTimeInterface
     * }>|array{}
     */
    private function getParentResourceTickets(array $parentResources, string $macroName): array
    {
        if ($parentResources === []) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery(array_values($parentResources), ':resource_id');

        $request = <<<SQL
            SELECT
                r.resource_id,
                tickets.ticket_id,
                tickets.timestamp,
                tickets.user,
                tickets_data.subject
            FROM `:dbstg`.resources r
            LEFT JOIN `:dbstg`.hosts h
                ON h.host_id = r.id
            LEFT JOIN `:dbstg`.customvariables cv
                ON cv.host_id = h.host_id
                AND (cv.service_id IS NULL OR cv.service_id = 0)
                AND cv.name = :macroName
            LEFT JOIN `:dbstg`.mod_open_tickets tickets
                ON tickets.ticket_value = cv.value
                AND (tickets.timestamp > h.last_time_up OR h.last_time_up IS NULL)
            LEFT JOIN `:dbstg`.mod_open_tickets_data tickets_data
                ON tickets_data.ticket_id = tickets.ticket_id
            WHERE r.resource_id IN ({$bindQuery})
                AND tickets.timestamp IS NOT NULL;
        SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':macroName', $macroName, \PDO::PARAM_STR);

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $tickets = [];
        while (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var _TicketData $record
             */
            $tickets[(int) $record['resource_id']] = [
                'id' => (int) $record['ticket_id'],
                'subject' => $record['subject'],
                'created_at' => (new \DateTimeImmutable())->setTimestamp((int) $record['timestamp'])
            ];
        }

        return $tickets;
    }

    /**
     * Get the name of the macro configured for the given rule ID.
     *
     * @param int $ruleId
     *
     * @return string
     */
    private function getMacroNameFromRuleId(int $ruleId): ?string
    {
        $request = <<<'SQL'
                SELECT `value` FROM `:db`.mod_open_tickets_form_value WHERE rule_id = :ruleId AND uniq_id = 'macro_ticket_id';
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        /** @var string|null|false $result */
        $result = $statement->fetchColumn();

        return $result !== false ? $result : null;
    }
}
