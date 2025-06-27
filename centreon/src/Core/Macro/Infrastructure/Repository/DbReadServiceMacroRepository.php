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
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;

/**
 * @phpstan-type _Macro array{
 *    svc_svc_id:int,
 *    svc_macro_name:string,
 *    svc_macro_value:string,
 *    is_password:int|null,
 *    description:string|null,
 *    macro_order:int,
 *    is_encryption_ready?:string
 * }
 */
class DbReadServiceMacroRepository extends DatabaseRepository implements ReadServiceMacroRepositoryInterface
{
    use SqlMultipleBindTrait;

    /**
     * @inheritDoc
     */
    public function findByServiceIds(int ...$serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        [$bindValues, $serviceIdsAsString] = $this->createMultipleBindQuery($serviceIds, ':serviceId_');
        $queryParams = QueryParameters::create([]);
        foreach ($bindValues as $key => $value) {
            /** @var int $value */
            $queryParams->add($key, QueryParameter::int($key, $value));
        }
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(
                <<<SQL
                    SELECT
                        m.svc_macro_name,
                        m.svc_macro_value,
                        m.is_password,
                        m.svc_svc_id,
                        m.description,
                        m.macro_order
                    FROM `:db`.on_demand_macro_service m
                    WHERE m.svc_svc_id IN ({$serviceIdsAsString})
                    SQL
            ),
            $queryParams
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var _Macro $result */
            $macros[] = $this->createMacro($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findPasswords(): array
    {
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(
                <<<'SQL'
                    SELECT
                            m.svc_macro_name,
                            m.svc_macro_value,
                            m.is_password,
                            m.svc_svc_id,
                            m.description,
                            m.macro_order
                    FROM `:db`.on_demand_macro_service m
                    WHERE m.is_password = 1
                    SQL
            )
        );

        $macros = [];
        foreach ($results as $result) {
            /** @var _Macro $result */
            $macros[] = $this->createMacro($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findServicesMacrosWithEncryptionReady(int $pollerId): array
    {
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(<<<SQL
                SELECT
                    odms.svc_svc_id,
                    odms.svc_macro_name,
                    odms.svc_macro_value,
                    odms.is_password,
                    odms.description,
                    odms.macro_order,
                    ns.is_encryption_ready
                FROM on_demand_macro_service odms
                INNER JOIN host_service_relation hsr
                    ON odms.svc_svc_id = hsr.service_service_id
                INNER JOIN ns_host_relation nsr
                    ON hsr.host_host_id = nsr.host_host_id
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
             *    svc_svc_id:int,
             *    svc_macro_name:string,
             *    svc_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int,
             *    is_encryption_ready:string
             * } $result */
            $macros[] = $this->createMacro($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findServiceTemplatesMacrosWithEncryptionReady(int $pollerId): array
    {
        $results = $this->connection->fetchAllAssociative(
            $this->translateDbName(<<<SQL
                SELECT
                    odms.svc_svc_id,
                    odms.svc_macro_name,
                    odms.svc_macro_value,
                    odms.is_password,
                    odms.description,
                    odms.macro_order,
                    ns.is_encryption_ready
                FROM on_demand_macro_service odms
                INNER JOIN service svc
                    ON odms.svc_svc_id = svc.service_template_model_stm_id
                INNER JOIN host_service_relation hsr
                    ON svc.service_id = hsr.service_service_id
                INNER JOIN ns_host_relation nsr
                    ON hsr.host_host_id = nsr.host_host_id
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
            $macros[] = $this->createMacro($result);
        }

        return $macros;
    }

    /**
     * @param _Macro $data
     *
     * @throws AssertionFailedException
     *
     * @return Macro
     */
    private function createMacro(array $data): Macro
    {
        preg_match('/^\$_SERVICE(?<macro_name>.*)\$$/', $data['svc_macro_name'], $matches);

        $macroName = $matches['macro_name'] ?? '';

        $macro = new Macro(
            (int) $data['svc_svc_id'],
            $macroName,
            $data['svc_macro_value'],
        );
        $shouldBeEncrypted = array_key_exists('is_encryption_ready', $data)
            && (bool) $data['is_password']
            && (bool) $data['is_encryption_ready'];
        $macro->setIsPassword($data['is_password'] === 1);
        $macro->setDescription($data['description'] ?? '');
        $macro->setOrder((int) $data['macro_order']);
        $macro->setShouldBeEncrypted($shouldBeEncrypted);

        return $macro;
    }
}
