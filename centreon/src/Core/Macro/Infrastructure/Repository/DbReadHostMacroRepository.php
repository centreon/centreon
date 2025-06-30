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

namespace Core\Macro\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;

class DbReadHostMacroRepository extends DatabaseRepository implements ReadHostMacroRepositoryInterface
{
    use LoggerTrait, SqlMultipleBindTrait;

    /**
     * @inheritDoc
     */
    public function findByHostIds(array $hostIds): array
    {
        $this->info('Get host macros',['host_ids' => $hostIds]);

        if ($hostIds === []) {
            return [];
        }

        [$bindValues, $hostIdsAsString] = $this->createMultipleBindQuery($hostIds, ':hostId_');
        $queryParams = QueryParameters::create([]);
        foreach ($bindValues as $key => $value) {
            /** @var int $value */
            $queryParams->add($key, QueryParameter::int($key, $value));
        }
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(
                <<<SQL
                    SELECT
                        m.host_macro_name,
                        m.host_macro_value,
                        m.is_password,
                        m.host_host_id,
                        m.description,
                        m.macro_order
                    FROM `:db`.on_demand_macro_host m
                    WHERE m.host_host_id IN ({$hostIdsAsString})
                    SQL
            ),
            $queryParams
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findByHostId(int $hostId): array
    {
        $this->info('Get host macros for a host/host template',['host_id' => $hostId]);

        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(
                <<<'SQL'
                    SELECT
                        m.host_macro_name,
                        m.host_macro_value,
                        m.is_password,
                        m.host_host_id,
                        m.description,
                        m.macro_order
                    FROM `:db`.on_demand_macro_host m
                    WHERE m.host_host_id = :host_id
                    SQL
            ),
            QueryParameters::create([QueryParameter::int('host_id', $hostId)])
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findPasswords(): array
    {
        $results = $this->connection->fetchAllAssociative($this->translateDbName(
            <<<'SQL'
                SELECT
                    m.host_macro_name,
                    m.host_macro_value,
                    m.is_password,
                    m.host_host_id,
                    m.description,
                    m.macro_order
                FROM `:db`.on_demand_macro_host m
                WHERE m.is_password = 1
                SQL
        ));

        $macros = [];
        foreach ($results as $result) {
            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findHostsMacrosWithEncryptionReady(int $pollerId): array
    {
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(<<<'SQL'
                SELECT
                    odmh.host_host_id,
                    odmh.host_macro_name,
                    odmh.host_macro_value,
                    odmh.is_password,
                    odmh.description,
                    odmh.macro_order,
                    ns.is_encryption_ready
                FROM on_demand_macro_host odmh
                INNER JOIN ns_host_relation nsr
                    ON odmh.host_host_id = nsr.host_host_id
                INNER JOIN nagios_server ns
                    ON nsr.nagios_server_id = ns.id
                WHERE ns.id = :pollerId
                SQL
            ),
            QueryParameters::create([QueryParameter::int('pollerId', $pollerId)])
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int,
             *    is_encryption_ready:string
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findHostTemplatesMacrosWithEncryptionReady(int $pollerId): array
    {
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(<<<'SQL'
                SELECT
                    odmh.host_host_id,
                    odmh.host_macro_name,
                    odmh.host_macro_value,
                    odmh.is_password,
                    odmh.description,
                    odmh.macro_order,
                    ns.is_encryption_ready
                FROM on_demand_macro_host odmh
                INNER JOIN host_template_relation htr
                    ON odmh.host_host_id = htr.host_tpl_id
                INNER JOIN ns_host_relation nsr
                    ON htr.host_host_id = nsr.host_host_id
                INNER JOIN nagios_server ns
                    ON nsr.nagios_server_id = ns.id
                WHERE odmh.host_host_id IN (
                    SELECT DISTINCT host_tpl_id FROM host_template_relation
                ) AND ns.id = :pollerId
                SQL
            ),
            QueryParameters::create([QueryParameter::int('pollerId', $pollerId)])
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int,
             *    is_encryption_ready:string
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @param array{
     *    host_host_id:int,
     *    host_macro_name:string,
     *    host_macro_value:string,
     *    is_password:int|null,
     *    description:string|null,
     *    macro_order:int,
     *    is_encryption_ready?:string
     * } $data
     *
     * @throws AssertionFailedException
     *
     * @return Macro
     */
    private function createHostMacroFromArray(array $data): Macro
    {
        preg_match('/^\$_HOST(?<macro_name>.*)\$$/', $data['host_macro_name'], $matches);

        $macroName = $matches['macro_name'] ?? '';

        $macro = new Macro(
            (int) $data['host_host_id'],
            $macroName,
            $data['host_macro_value'],
        );
        $shouldBeEncrypted = array_key_exists('is_encryption_ready', $data)
            && (bool) $data['is_password']
            && (bool) $data['is_encryption_ready'];
        $macro->setIsPassword((bool) $data['is_password']);
        $macro->setDescription($data['description'] ?? '');
        $macro->setOrder($data['macro_order']);
        $macro->setShouldBeEncrypted($shouldBeEncrypted);

        return $macro;
    }
}
