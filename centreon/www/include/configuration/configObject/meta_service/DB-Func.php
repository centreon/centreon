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

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonMeta.class.php';

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\ValueObjectException;

/**
 * Determine whether a meta service name is available.
 *
 * Checks for an existing row in `meta_service` with the given name and, if the current form
 * provides `meta_id`, excludes that ID from the check.
 *
 * @param string|null $name The meta service name to check.
 * @return bool `true` if no matching `meta_service` exists (taking the optional form `meta_id` exclusion into account), `false` otherwise.
 */
function testExistence($name = null)
{
    global $pearDB, $form;
    $metaIdFromForm = $form ? $form->getSubmitValue('meta_id') : null;
    $qb = $pearDB->createQueryBuilder();
    $qb->select('1')
        ->from('meta_service')
        ->where('meta_name = :meta_name');
    if ($metaIdFromForm !== null) {
        $qb->andWhere('meta_id <> :meta_id');
    }
    $query = $qb->getQuery();
    try {
        $params = [QueryParameter::string('meta_name', getParamValue($name, sanitize: true))];
        if ($metaIdFromForm !== null) {
            $params[] = QueryParameter::int('meta_id', (int) $metaIdFromForm);
        }
        $meta = $pearDB->fetchAssociative($query, QueryParameters::create($params));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while executing testExistence',
            [
                'metaName' => $name,
            ],
            $exception
        );
        $meta = false;
    }

    return $meta === false;
}

/**
 * Enable a meta service in the DB
 *
 * @param int|null $metaId
 * @return void
 */
function enableMetaServiceInDB($metaId = null)
{
    if (! $metaId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('meta_service')
        ->set('meta_activate', "'1'")
        ->where('meta_id = :meta_id')
        ->getQuery();
    try {
        $pearDB->update($query, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId),
        ]));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while enabling meta_service',
            [
                'metaId' => $metaId,
            ],
            $exception
        );
    }
}

/**
 * Disable a meta service in the DB
 *
 * @param int|null $metaId
 * @return void
 */
function disableMetaServiceInDB($metaId = null)
{
    if (! $metaId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('meta_service')
        ->set('meta_activate', "'0'")
        ->where('meta_id = :meta_id')
        ->getQuery();
    try {
        $pearDB->update($query, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId),
        ]));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while disabling meta_service',
            [
                'metaId' => $metaId,
            ],
            $exception
        );
    }
}

/**
 * Remove dependency relation if it is the last one
 *
 * @param int $serviceId
 * @return void
 */
function removeRelationLastMetaServiceDependency(int $serviceId): void
{
    global $pearDB;
    $subQb = $pearDB->createQueryBuilder();
    $subQuery = $subQb->select('dependency_dep_id')
        ->from('dependency_metaserviceParent_relation')
        ->where('meta_service_meta_id = :serviceId')
        ->getQuery();

    $qb = $pearDB->createQueryBuilder();
    $query = $qb->select('COUNT(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id')
        ->from('dependency_metaserviceParent_relation')
        ->where('dependency_dep_id = (' . $subQuery . ')')
        ->groupBy('dependency_dep_id')
        ->getQuery();
    try {
        $result = $pearDB->fetchAssociative($query, QueryParameters::create([
            QueryParameter::int('serviceId', $serviceId),
        ]));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error in removeRelationLastMetaServiceDependency',
            [
                'serviceId' => $serviceId,
            ],
            $exception
        );

        return;
    }
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $qbDel = $pearDB->createQueryBuilder();
        $queryDel = $qbDel->delete('dependency')
            ->where('dep_id = :dep_id')
            ->getQuery();
        try {
            $pearDB->delete($queryDel, QueryParameters::create([
                QueryParameter::int('dep_id', (int) $result['id']),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting dependency',
                [
                    'depId' => $result['id'],
                ],
                $exception
            );
        }
    }
}

/**
 * Remove meta_service records identified by the array keys and delete their associated virtual service entries.
 *
 * Deletes each meta_service row whose ID is provided as a key in the $metas array and removes the corresponding
 * service row where service_description is "meta_<id>" and service_register = '2'.
 *
 * @param array<int, mixed> $metas Array indexed by meta_service IDs; values are ignored.
 */
function deleteMetaServiceInDB($metas = [])
{
    global $pearDB;
    foreach ($metas as $metaId => $value) {
        removeRelationLastMetaServiceDependency((int) $metaId);
        $qb = $pearDB->createQueryBuilder();
        $query = $qb->delete('meta_service')
            ->where('meta_id = :meta_id')
            ->getQuery();
        try {
            $pearDB->delete($query, QueryParameters::create([
                QueryParameter::int('meta_id', (int) $metaId),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting meta_service',
                [
                    'meta_id' => $metaId,
                ],
                $exception
            );
        }
        $qb2 = $pearDB->createQueryBuilder();
        $query2 = $qb2->delete('service')
            ->where('service_description = :service_description')
            ->andWhere("service_register = '2'")
            ->getQuery();
        try {
            $pearDB->delete($query2, QueryParameters::create([
                QueryParameter::string('service_description', 'meta_' . $metaId),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting service for meta_service',
                [
                    'serviceDescription' => 'meta_' . $metaId,
                ],
                $exception
            );
        }
    }
}

/**
 * Enable a metric in the DB
 *
 * @param int|null $msrId
 * @return void
 */
function enableMetricInDB($msrId = null)
{
    if (! $msrId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('meta_service_relation')
        ->set('activate', "'1'")
        ->where('msr_id = :msr_id')
        ->getQuery();
    try {
        $pearDB->update($query, QueryParameters::create([
            QueryParameter::int('msr_id', (int) $msrId),
        ]));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error enabling metric',
            [
                'msrId' => $msrId,
            ],
            $exception
        );
    }
}

/**
 * Disable a metric in the DB
 *
 * @param int|null $msrId
 * @return void
 */
function disableMetricInDB($msrId = null)
{
    if (! $msrId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('meta_service_relation')
        ->set('activate', "'0'")
        ->where('msr_id = :msr_id')
        ->getQuery();
    try {
        $pearDB->update($query, QueryParameters::create([
            QueryParameter::int('msr_id', (int) $msrId),
        ]));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error disabling metric',
            [
                'msrId' => $msrId,
            ],
            $exception
        );
    }
}

/**
 * Delete metric(s) from the DB
 *
 * @param array<int, mixed> $metrics metric IDs as keys
 * @return void
 */
function deleteMetricInDB($metrics = [])
{
    global $pearDB;
    foreach ($metrics as $msrId => $value) {
        $qb = $pearDB->createQueryBuilder();
        $query = $qb->delete('meta_service_relation')
            ->where('msr_id = :msr_id')
            ->getQuery();
        try {
            $pearDB->delete($query, QueryParameters::create([
                QueryParameter::int('msr_id', (int) $msrId),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting metric',
                [
                    'msrId' => $msrId,
                ],
                $exception
            );
        }
    }
}

/**
 * Create copies of specified meta services.
 *
 * For each meta ID provided in `$metas`, creates the requested number of duplicates (from `$nbrDup`), each with a unique suffixed name; for each created duplicate a virtual service is added and associated contacts, contact groups, and metrics are duplicated, then ACL-resource relations for the new meta are updated. Invalid meta IDs are skipped. Each duplicate is created inside a transaction and failures are logged; if the function cannot find enough unique suffixes it stops attempting further copies for that meta.
 *
 * @param array<int, mixed> $metas Array keyed by meta_id; keys identify which meta services to duplicate.
 * @param array<int, int> $nbrDup Map of meta_id => number of copies to create for that meta.
 * @return void
 */
function multipleMetaServiceInDB($metas = [], $nbrDup = [])
{
    global $pearDB;
    foreach ($metas as $metaId => $value) {
        $validMetaId = filter_var($metaId, FILTER_VALIDATE_INT);
        if ($validMetaId === false) {
            continue;
        }

        $qbSelect = $pearDB->createQueryBuilder();
        $query = $qbSelect->select('*')
            ->from('meta_service')
            ->where('meta_id = :meta_id')
            ->limit(1)
            ->getQuery();
        try {
            $row = $pearDB->fetchAssociative($query, QueryParameters::create([
                QueryParameter::int('meta_id', $validMetaId),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching meta_service for duplication',
                [
                    'metaId' => $metaId,
                ],
                $exception
            );
            continue;
        }
        if (! $row) {
            continue;
        }
        unset($row['meta_id']);
        $copies = filter_var($nbrDup[$metaId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if (! $copies) {
            continue;
        }
        $suffix = 1;
        for ($i = 0; $i < $copies && $suffix <= $copies + 1000; $suffix++) {
            $metaName = $row['meta_name'] . '_' . $suffix;
            $row['meta_name'] = $metaName;
            $columns = array_keys($row);
            $qbInsert = $pearDB->createQueryBuilder();
            $insertQuery = $qbInsert->insert('meta_service')
                ->values(array_combine($columns, array_map(fn ($col) => ':' . $col, $columns)))
                ->getQuery();

            try {
                if (! testExistence($metaName)) {
                    continue;
                }
                $i++;
                $params = [];
                foreach ($row as $column => $value) {
                    $params[] = QueryParameter::string($column, $value);
                }

                $newMetaId = null;
                $pearDB->startTransaction();
                try {
                    $pearDB->insert($insertQuery, QueryParameters::create($params));
                    $newMetaId = $pearDB->getLastInsertId();
                    if ($newMetaId) {
                        $metaObj = new CentreonMeta($pearDB);
                        $metaObj->insertVirtualService($newMetaId, $metaName);

                        // Duplicate contacts
                        $qbContacts = $pearDB->createQueryBuilder();
                        $queryContacts = $qbContacts->select('DISTINCT contact_id')
                            ->from('meta_contact')
                            ->where('meta_id = :meta_id')
                            ->getQuery();
                        $contacts = $pearDB->fetchAllAssociative($queryContacts, QueryParameters::create([
                            QueryParameter::int('meta_id', $validMetaId),
                        ]));
                        foreach ($contacts as $contact) {
                            $qbInsertContact = $pearDB->createQueryBuilder();
                            $queryInsertContact = $qbInsertContact->insert('meta_contact')
                                ->values([
                                    'meta_id'    => ':meta_id',
                                    'contact_id' => ':contact_id',
                                ])
                                ->getQuery();
                            $pearDB->insert($queryInsertContact, QueryParameters::create([
                                QueryParameter::int('meta_id', (int) $newMetaId),
                                QueryParameter::int('contact_id', (int) $contact['contact_id']),
                            ]));
                        }

                        // Duplicate contactgroups
                        $qbCG = $pearDB->createQueryBuilder();
                        $queryCG = $qbCG->select('DISTINCT cg_cg_id')
                            ->from('meta_contactgroup_relation')
                            ->where('meta_id = :meta_id')
                            ->getQuery();
                        $cgroups = $pearDB->fetchAllAssociative($queryCG, QueryParameters::create([
                            QueryParameter::int('meta_id', $validMetaId),
                        ]));
                        foreach ($cgroups as $cg) {
                            $qbInsertCG = $pearDB->createQueryBuilder();
                            $queryInsertCG = $qbInsertCG->insert('meta_contactgroup_relation')
                                ->values([
                                    'meta_id'   => ':meta_id',
                                    'cg_cg_id'  => ':cg_cg_id',
                                ])
                                ->getQuery();
                            $pearDB->insert($queryInsertCG, QueryParameters::create([
                                QueryParameter::int('meta_id', (int) $newMetaId),
                                QueryParameter::int('cg_cg_id', (int) $cg['cg_cg_id']),
                            ]));
                        }

                        // Duplicate metrics
                        $qbMetric = $pearDB->createQueryBuilder();
                        $queryMetric = $qbMetric->select('*')
                            ->from('meta_service_relation')
                            ->where('meta_id = :meta_id')
                            ->getQuery();
                        $metricsRows = $pearDB->fetchAllAssociative($queryMetric, QueryParameters::create([
                            QueryParameter::int('meta_id', $validMetaId),
                        ]));
                        foreach ($metricsRows as $metric) {
                            unset($metric['msr_id']);
                            $metric['meta_id'] = $newMetaId;
                            $columns = array_keys($metric);
                            $qbInsertMetric = $pearDB->createQueryBuilder();
                            $insertMetricQuery = $qbInsertMetric->insert('meta_service_relation')
                                ->values(array_combine($columns, array_map(fn ($col) => ':' . $col, $columns)))
                                ->getQuery();
                            // Build parameters for the metric row.
                            $paramsMetric = [];
                            foreach ($metric as $column => $value) {
                                $paramsMetric[] =  QueryParameter::string($column, $value);
                            }
                            $pearDB->insert($insertMetricQuery, QueryParameters::create($paramsMetric));
                        }
                    }
                    $pearDB->commitTransaction();
                } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                    if ($pearDB->isTransactionActive()) {
                        $pearDB->rollBackTransaction();
                    }

                    throw $exception;
                }

                if ($newMetaId) {
                    updateAclResourcesMetaRelations($newMetaId);
                }
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error duplicating meta_service',
                    [
                        'metaId' => $metaId,
                        'metaName' => $metaName,
                    ],
                    $exception
                );
            }
        }
        if ($i < $copies) {
            error_log("Could only create {$i}/{$copies} duplicates for meta service '{$row['meta_name']}' ({$metaId}): suffix search exhausted");
        }
    }
}

/**
 * Update an existing meta service using bound parameters
 *
 * @param int|null $metaId
 * @return void
 */
function updateMetaServiceInDB($metaId = null)
{
    global $isCloudPlatform;

    if (! $metaId) {
        return;
    }
    updateMetaService($metaId);

    if (! $isCloudPlatform) {
        updateMetaServiceContact($metaId);
        updateMetaServiceContactGroup($metaId);
    }
    updateAclResourcesMetaRelations($metaId);
}

/**
 * Insert a new meta service in the DB
 *
 * @return int
 */
function insertMetaServiceInDB()
{
    global $isCloudPlatform;

    $metaId = insertMetaService();

    if (! $isCloudPlatform) {
        updateMetaServiceContact($metaId);
        updateMetaServiceContactGroup($metaId);
    }
    updateAclResourcesMetaRelations($metaId);

    return $metaId;
}

/**
 * Duplicate meta service metrics for the given metric IDs according to requested counts.
 *
 * Inserts the specified number of copies for each metric identified by the keys of `$metrics`; each copy preserves the original metric fields except for the primary key.
 *
 * @param array<int,mixed> $metrics Array keyed by `msr_id`; only the keys are used to identify metrics to duplicate.
 * @param array<int,int> $nbrDup Mapping of `msr_id` to the number of copies to create (validated to be between 0 and 100).
 */
function multipleMetricInDB($metrics = [], $nbrDup = [])
{
    global $pearDB;
    foreach ($metrics as $msrId => $value) {
        $qbSelect = $pearDB->createQueryBuilder();
        $query = $qbSelect->select('*')
            ->from('meta_service_relation')
            ->where('msr_id = :msr_id')
            ->limit(1)
            ->getQuery();
        try {
            $row = $pearDB->fetchAssociative($query, QueryParameters::create([
                QueryParameter::int('msr_id', (int) $msrId),
            ]));
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching metric for duplication',
                [
                    'msrId' => $msrId,
                ],
                $exception
            );
            continue;
        }
        if (! $row) {
            continue;
        }
        unset($row['msr_id']);
        $copies = filter_var($nbrDup[$msrId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if (! $copies) {
            continue;
        }
        for ($i = 0; $i < $copies; $i++) {
            $columns = array_keys($row);
            $qbInsert = $pearDB->createQueryBuilder();
            $insertQuery = $qbInsert->insert('meta_service_relation')
                ->values(array_combine($columns, array_map(fn ($col) => ':' . $col, $columns)))
                ->getQuery();
            try {
                $params = [];
                foreach ($row as $column => $val) {
                    $params[] = QueryParameter::string($column, $val);
                }
                $pearDB->insert($insertQuery, QueryParameters::create($params));
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error inserting duplicated metric',
                    [
                        'originalMsrId' => $msrId,
                        'duplicationIndex' => $i + 1,
                    ],
                    $exception
                );
            }
        }
    }
}

/**
 * Check if the virtual meta host exists and create it if not
 *
 * @return void
 */
function checkMetaHost()
{
    global $pearDB;
    $qbSelect = $pearDB->createQueryBuilder();
    $query = $qbSelect->select('host_id')
        ->from('host')
        ->where("host_register = '2'")
        ->andWhere("host_name = '_Module_Meta'")
        ->getQuery();
    try {
        $host = $pearDB->fetchAssociative($query);
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error fetching _Module_Meta host',
            [
            ],
            $exception
        );
        $host = false;
    }
    if (! $host) {
        $qbInsert = $pearDB->createQueryBuilder();
        $queryInsert = $qbInsert->insert('host')
            ->values([
                'host_name' => "'_Module_Meta'",
                'host_register' => "'2'",
            ])
            ->getQuery();
        try {
            $pearDB->insert($queryInsert);
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error inserting _Module_Meta host',
                [
                ],
                $exception
            );
        }

        // For linking, the subqueries are left as raw SQL for clarity.
        $queryLink = <<<'SQL'
                INSERT INTO ns_host_relation (nagios_server_id, host_host_id)
                VALUES (
                    (SELECT id FROM nagios_server WHERE localhost = '1'),
                    (SELECT host_id FROM host WHERE host_name = '_Module_Meta')
                )
                ON DUPLICATE KEY UPDATE nagios_server_id = (SELECT id FROM nagios_server WHERE localhost = '1')
            SQL;
        try {
            $pearDB->insert($queryLink);
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error linking _Module_Meta host to Nagios server',
                [
                ],
                $exception
            );
        }
    }
}

/**
 * Insert a new meta service record, create its virtual service, and log the creation.
 *
 * If `$ret` is empty, submitted form values are used.
 *
 * @param array<mixed> $ret Optional associative array of meta fields (keys correspond to `meta_service` columns).
 * @return int The new `meta_id`, or `0` if the insert failed.
 */
function insertMetaService($ret = [])
{
    global $form, $pearDB, $centreon;
    checkMetaHost();
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $qbInsert = $pearDB->createQueryBuilder();
    $query = $qbInsert->insert('meta_service')
        ->values([
            'meta_name' => ':meta_name',
            'meta_display' => ':meta_display',
            'check_period' => ':check_period',
            'max_check_attempts' => ':max_check_attempts',
            'normal_check_interval' => ':normal_check_interval',
            'retry_check_interval' => ':retry_check_interval',
            'notification_interval' => ':notification_interval',
            'notification_period' => ':notification_period',
            'notification_options' => ':notification_options',
            'notifications_enabled' => ':notifications_enabled',
            'calcul_type' => ':calcul_type',
            'data_source_type' => ':data_source_type',
            'meta_select_mode' => ':meta_select_mode',
            'regexp_str' => ':regexp_str',
            'metric' => ':metric',
            'warning' => ':warning',
            'critical' => ':critical',
            'graph_id' => ':graph_id',
            'meta_comment' => ':meta_comment',
            'geo_coords' => ':geo_coords',
            'meta_activate' => ':meta_activate',
        ])
        ->getQuery();

    try {
        $params = [
            QueryParameter::string('meta_name', getParamValue($ret, 'meta_name', sanitize: true)),
            QueryParameter::string('meta_display', getParamValue($ret, 'meta_display', sanitize: true)),
            QueryParameter::string('check_period', getParamValue($ret, 'check_period')),
            QueryParameter::int('max_check_attempts', (int) getParamValue($ret, 'max_check_attempts')),
            QueryParameter::string('normal_check_interval', getParamValue($ret, 'normal_check_interval')),
            QueryParameter::string('retry_check_interval', getParamValue($ret, 'retry_check_interval')),
            QueryParameter::string('notification_interval', getParamValue($ret, 'notification_interval')),
            QueryParameter::string('notification_period', getParamValue($ret, 'notification_period')),
            QueryParameter::string('notification_options', isset($ret['ms_notifOpts']) ? implode(',', array_keys($ret['ms_notifOpts'])) : null),
            QueryParameter::string('notifications_enabled', getParamValue($ret, 'notifications_enabled', 'notifications_enabled', default: '2')),
            QueryParameter::string('calcul_type', $ret['calcul_type'] ?? null),
            QueryParameter::int('data_source_type', (int) getParamValue($ret, 'data_source_type', default: 0)),
            QueryParameter::string('meta_select_mode', getParamValue($ret, 'meta_select_mode', 'meta_select_mode')),
            QueryParameter::string('regexp_str', getParamValue($ret, 'regexp_str', sanitize: true)),
            QueryParameter::string('metric', getParamValue($ret, 'metric', sanitize: true)),
            QueryParameter::string('warning', getParamValue($ret, 'warning', sanitize: true)),
            QueryParameter::string('critical', getParamValue($ret, 'critical', sanitize: true)),
            QueryParameter::string('graph_id', getParamValue($ret, 'graph_id')),
            QueryParameter::string('meta_comment', getParamValue($ret, 'meta_comment', sanitize: true)),
            QueryParameter::string('geo_coords', getParamValue($ret, 'geo_coords', sanitize: true)),
            QueryParameter::string('meta_activate', getParamValue($ret, 'meta_activate', 'meta_activate')),
        ];
        $pearDB->insert($query, QueryParameters::create($params));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting meta_service',
            [
                'metaName' => $ret['meta_name'] ?? null,
                'params'   => $params,
            ],
            $exception
        );
    }
    $metaId = $pearDB->getLastInsertId();
    if (! $metaId) {
        return 0;
    }
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('meta', $metaId, $ret['meta_name'], 'a', $fields);
    $metaObj = new CentreonMeta($pearDB);
    $metaObj->insertVirtualService($metaId, $ret['meta_name']);

    return $metaId;
}

/**
 * Update a meta service record using current form values, record the change, and refresh its virtual service.
 *
 * Updates the meta_service row identified by $metaId with submitted form values, inserts an action log entry for the change, and updates the associated virtual service entry. Does nothing if $metaId is null or falsy.
 *
 * @param int|null $metaId The ID of the meta service to update; if null or falsy the function returns without action.
 */
function updateMetaService($metaId = null)
{
    if (! $metaId) {
        return;
    }
    global $form, $pearDB, $centreon;
    checkMetaHost();
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $qb->update('meta_service')
        ->set('meta_name', ':meta_name')
        ->set('meta_display', ':meta_display')
        ->set('check_period', ':check_period')
        ->set('max_check_attempts', ':max_check_attempts')
        ->set('normal_check_interval', ':normal_check_interval')
        ->set('retry_check_interval', ':retry_check_interval')
        ->set('notification_interval', ':notification_interval')
        ->set('notification_period', ':notification_period')
        ->set('notification_options', ':notification_options')
        ->set('notifications_enabled', ':notifications_enabled')
        ->set('calcul_type', ':calcul_type')
        ->set('data_source_type', ':data_source_type')
        ->set('meta_select_mode', ':meta_select_mode')
        ->set('regexp_str', ':regexp_str')
        ->set('metric', ':metric')
        ->set('warning', ':warning')
        ->set('critical', ':critical')
        ->set('graph_id', ':graph_id')
        ->set('meta_comment', ':meta_comment')
        ->set('geo_coords', ':geo_coords')
        ->set('meta_activate', ':meta_activate')
        ->where('meta_id = :meta_id');
    $query = $qb->getQuery();
    $params = [];
    try {
        $params = [
            QueryParameter::string('meta_name', getParamValue($ret, 'meta_name', sanitize: true)),
            QueryParameter::string('meta_display', getParamValue($ret, 'meta_display', sanitize: true)),
            QueryParameter::string('check_period', getParamValue($ret, 'check_period')),
            QueryParameter::int('max_check_attempts', (int) getParamValue($ret, 'max_check_attempts')),
            QueryParameter::string('normal_check_interval', getParamValue($ret, 'normal_check_interval')),
            QueryParameter::string('retry_check_interval', getParamValue($ret, 'retry_check_interval')),
            QueryParameter::string('notification_interval', getParamValue($ret, 'notification_interval')),
            QueryParameter::string('notification_period', getParamValue($ret, 'notification_period')),
            QueryParameter::string('notification_options', isset($ret['ms_notifOpts']) ? implode(',', array_keys($ret['ms_notifOpts'])) : null),
            QueryParameter::string('notifications_enabled', getParamValue($ret, 'notifications_enabled', 'notifications_enabled', false, '2')),
            QueryParameter::string('calcul_type', $ret['calcul_type'] ?? null),
            QueryParameter::int('data_source_type', (int) getParamValue($ret, 'data_source_type', null, false, 0)),
            QueryParameter::string('meta_select_mode', getParamValue($ret, 'meta_select_mode', 'meta_select_mode')),
            QueryParameter::string('regexp_str', getParamValue($ret, 'regexp_str', sanitize: true)),
            QueryParameter::string('metric', getParamValue($ret, 'metric', sanitize: true)),
            QueryParameter::string('warning', getParamValue($ret, 'warning', sanitize: true)),
            QueryParameter::string('critical', getParamValue($ret, 'critical', sanitize: true)),
            QueryParameter::string('graph_id', getParamValue($ret, 'graph_id')),
            QueryParameter::string('meta_comment', getParamValue($ret, 'meta_comment', sanitize: true)),
            QueryParameter::string('geo_coords', getParamValue($ret, 'geo_coords', sanitize: true)),
            QueryParameter::string('meta_activate', getParamValue($ret, 'meta_activate', 'meta_activate')),
            QueryParameter::int('meta_id', (int) $metaId),
        ];
        $pearDB->update($query, QueryParameters::create($params));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating meta_service (updateMetaService)',
            [
                'metaId' => $metaId,
                'params' => $params,
            ],
            $exception
        );
    }
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog('meta', $metaId, $ret['meta_name'], 'c', $fields);
    $metaObj = new CentreonMeta($pearDB);
    $metaObj->insertVirtualService($metaId, $ret['meta_name']);
}

/**
 * Update meta service contact relations for a given meta service.
 *
 * Deletes existing contact relations for the specified meta and inserts the submitted contact list;
 * ensures the current user is included in the list unless the user is an administrator.
 *
 * The operation is performed inside a database transaction; on failure the transaction is rolled back and an SQL error is logged.
 *
 * @param int $metaId The meta service identifier to update.
 */
function updateMetaServiceContact($metaId)
{
    if (! $metaId || ! is_numeric($metaId)) {
        return;
    }
    global $form, $pearDB, $centreon;

    $pearDB->startTransaction();
    try {
        $qbDelete = $pearDB->createQueryBuilder();
        $queryPurge = $qbDelete->delete('meta_contact')
            ->where('meta_id = :meta_id')
            ->getQuery();
        $pearDB->delete($queryPurge, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId),
        ]));

        $ret = CentreonUtils::mergeWithInitialValues($form, 'ms_cs');
        $userId = $centreon->user->get_id();
        if (! in_array($userId, $ret) && $centreon->user->admin !== '1') {
            $ret[] = $userId;
        }

        $values = [];
        $params = [];
        foreach ($ret as $key => $contactId) {
            $values[] = " (:metaId_{$key}, :contactId_{$key})";
            $params["metaId_{$key}"] = QueryParameter::int("metaId_{$key}", (int) $metaId);
            $params["contactId_{$key}"] = QueryParameter::int("contactId_{$key}", (int) $contactId);
        }
        if ($values !== []) {
            $valuesString = implode(',', $values);
            $queryAddRelation = "INSERT INTO meta_contact (meta_id, contact_id) VALUES {$valuesString}";
            $pearDB->insert($queryAddRelation, QueryParameters::create(array_values($params)));
        }

        $pearDB->commitTransaction();
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating Meta Service Contact',
            [
                'metaId' => $metaId,
            ],
            $exception
        );
    }
}

/**
 * Update contact-group relations for a meta service.
 *
 * Deletes existing contact-group relations for the provided meta ID and inserts relations
 * from the form (merged with initial values). Non-numeric group identifiers are treated
 * as LDAP group names and an LDAP group will be created; groups that fail creation are skipped.
 * The operation runs inside a database transaction and will roll back on error; failures are logged.
 *
 * @param int|null $metaId ID of the meta service to update; if null or falsy, the function does nothing.
 */
function updateMetaServiceContactGroup($metaId = null)
{
    if (! $metaId) {
        return;
    }
    global $form, $pearDB;

    $pearDB->startTransaction();
    try {
        $qbDelete = $pearDB->createQueryBuilder();
        $queryDelete = $qbDelete->delete('meta_contactgroup_relation')
            ->where('meta_id = :meta_id')
            ->getQuery();
        $pearDB->delete($queryDelete, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId),
        ]));

        $ret = CentreonUtils::mergeWithInitialValues($form, 'ms_cgs');
        $cg = new CentreonContactgroup($pearDB);
        $qbInsert = $pearDB->createQueryBuilder();
        $queryInsert = $qbInsert->insert('meta_contactgroup_relation')
            ->values([
                'meta_id' => ':meta_id',
                'cg_cg_id' => ':cg_cg_id',
            ])
            ->getQuery();
        foreach ($ret as $group) {
            if (! is_numeric($group)) {
                $res = $cg->insertLdapGroup($group);
                if ($res != 0) {
                    $group = $res;
                } else {
                    continue;
                }
            }
            $pearDB->insert($queryInsert, QueryParameters::create([
                QueryParameter::int('meta_id', (int) $metaId),
                QueryParameter::int('cg_cg_id', (int) $group),
            ]));
        }

        $pearDB->commitTransaction();
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating Meta Service Contact Group',
            [
                'metaId' => $metaId,
            ],
            $exception
        );
    }
}

/**
 * Update ACL-to-meta resource relations for the current (non-admin) user based on their accessible ACL groups.
 *
 * Fetches active ACL resources visible to the current user's groups, removes any existing relations for
 * those resources and the given meta, and inserts new relations within a transaction.
 *
 * @param int $metaId The meta service identifier to update ACL resource relations for.
 *
 * @throws ValueObjectException|CollectionException|ConnectionException If database operations fail during the transactional update.
 */
function updateAclResourcesMetaRelations(int $metaId): void
{
    global $pearDB, $centreon;
    if ($metaId <= 0 || $centreon->user->admin === '1') {
        return;
    }

    // get ACL resources IDs for the current user
    $acl = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $accessGroupIds = array_filter(
        explode(',', $acl->getAccessGroupsString('ID')),
        fn ($id) => is_numeric(trim($id))
    );
    $accessGroupIds = array_map(fn ($id) => (int) trim($id), $accessGroupIds);

    if ($accessGroupIds === []) {
        return;
    }

    $aclGroupParams = [];
    $aclGroupPlaceholders = [];
    foreach ($accessGroupIds as $idx => $groupId) {
        $key = 'aclGroupId' . $idx;
        $aclGroupPlaceholders[] = ':' . $key;
        $aclGroupParams[] = QueryParameter::int($key, $groupId);
    }

    $selectAclQuery = "SELECT DISTINCT ar.acl_res_id
            FROM acl_res_group_relations argr
            INNER JOIN acl_resources ar on ar.acl_res_id = argr.acl_res_id and ar.acl_res_activate = '1'
            WHERE acl_group_id IN (" . implode(', ', $aclGroupPlaceholders) . ')';
    try {
        $aclResIds = $pearDB->fetchAllAssociative($selectAclQuery, QueryParameters::create($aclGroupParams));
        if ($aclResIds !== []) {
            // clean old relations
            $deleteParams = [QueryParameter::int('metaId', (int) $metaId)];
            $deletePlaceholders = [];
            foreach ($aclResIds as $idx => $row) {
                $key = 'aclResId' . $idx;
                $deletePlaceholders[] = ':' . $key;
                $deleteParams[] = QueryParameter::int($key, (int) $row['acl_res_id']);
            }

            $paramsAcl = [];
            $values = [];
            foreach ($aclResIds as $idx => $aclResId) {
                $key = 'aclResId' . $idx;
                $metaKey = 'metaId' . $idx;
                $values[] = " (:{$key}, :{$metaKey})";
                $paramsAcl[] = QueryParameter::int($key, (int) $aclResId['acl_res_id']);
                $paramsAcl[] = QueryParameter::int($metaKey, (int) $metaId);
            }

            $pearDB->startTransaction();
            try {
                $queryClean = 'DELETE FROM acl_resources_meta_relations WHERE meta_id = :metaId AND acl_res_id IN (' . implode(', ', $deletePlaceholders) . ')';
                $pearDB->delete($queryClean, QueryParameters::create($deleteParams));

                // insert new relations
                if ($values !== []) {
                    $valuesString = implode(',', $values);
                    $queryAcl = "INSERT INTO acl_resources_meta_relations (acl_res_id, meta_id) VALUES {$valuesString}";
                    $pearDB->insert($queryAcl, QueryParameters::create($paramsAcl));
                }
                $pearDB->commitTransaction();
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                if ($pearDB->isTransactionActive()) {
                    $pearDB->rollBackTransaction();
                }

                throw $exception;
            }
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating acl_resources_meta_relations',
            [
                'metaId' => $metaId,
            ],
            $exception
        );
    }
}

/**
 * Update metric – simply calls updateMetric
 *
 * @param int|null $msrId
 * @return void
 */
function updateMetricInDB($msrId = null)
{
    if (! $msrId) {
        return;
    }
    updateMetric($msrId);
}

// /**
//  * Insert metric – inserts then updates its contact groups
//  * Not used
//  *
//  * @return int
//  */
// function insertMetricInDB()
// {
//     $msrId = insertMetric();
//     updateMetricContactGroup($msrId);
//     return $msrId;
// }

/**
 * Insert a new metric into the meta_service_relation table.
 *
 * If `$ret` is empty, submitted form values are used to build the inserted row.
 *
 * @param array<mixed> $ret Optional associative array of metric fields (meta_id, host_id, metric_sel, msr_comment, activate).
 * @return int The new metric row's `msr_id`, or 0 if the insertion failed.
 */
function insertMetric($ret = [])
{
    global $form, $pearDB, $centreon;
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->insert('meta_service_relation')
        ->values([
            'meta_id' => ':meta_id',
            'host_id' => ':host_id',
            'metric_id' => ':metric_id',
            'msr_comment' => ':msr_comment',
            'activate' => ':activate',
        ])
        ->getQuery();
    try {
        $params = [
            QueryParameter::int('meta_id', (int) getParamValue($ret, 'meta_id')),
            QueryParameter::int('host_id', (int) getParamValue($ret, 'host_id')),
            QueryParameter::int('metric_id', (int) getParamValue($ret, 'metric_sel', 1)),
            QueryParameter::string('msr_comment', getParamValue($ret, 'msr_comment', sanitize: true)),
            QueryParameter::string('activate', getParamValue($ret, 'activate', 'activate')),
        ];
        $pearDB->insert($query, QueryParameters::create($params));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting metric',
            [
                'params' => $params,
            ],
            $exception
        );
    }
    $msrId = $pearDB->getLastInsertId();
    if (! $msrId) {
        return 0;
    }

    return $msrId;
}

/**
 * Update a metric
 *
 * @param int|null $msrId
 * @return void
 */
function updateMetric($msrId = null)
{
    if (! $msrId) {
        return;
    }
    global $form, $pearDB;
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update('meta_service_relation')
        ->set('meta_id', ':meta_id')
        ->set('host_id', ':host_id')
        ->set('metric_id', ':metric_id')
        ->set('msr_comment', ':msr_comment')
        ->set('activate', ':activate')
        ->where('msr_id = :msr_id')
        ->getQuery();
    try {
        $params = [
            QueryParameter::int('meta_id', (int) getParamValue($ret, 'meta_id')),
            QueryParameter::int('host_id', (int) getParamValue($ret, 'host_id')),
            QueryParameter::int('metric_id', (int) getParamValue($ret, 'metric_sel', 1)),
            QueryParameter::string('msr_comment', getParamValue($ret, 'msr_comment', sanitize: true)),
            QueryParameter::string('activate', getParamValue($ret, 'activate', 'activate')),
            QueryParameter::int('msr_id', (int) $msrId),
        ];
        $pearDB->update($query, QueryParameters::create($params));
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating metric',
            [
                'msrId' => $msrId,
                'params' => $params,
            ],
            $exception
        );
    }
}

/**
 * Retrieve a value from an array by an optional first-level key and optional subkey, optionally applying HTML escaping.
 *
 * If $key is null or $params is not an array, the raw $params value is returned (optionally sanitized). When $subKey is provided,
 * the function returns the nested value at $params[$key][$subKey] if it exists and is not an empty string or null. Otherwise it
 * returns the first-level value at $params[$key] if present and not an empty string or null. If no usable value is found, $default
 * is returned.
 *
 * @param array<mixed>|mixed $params The parameter array to read from, or a scalar value returned directly when $key is null or $params is not an array.
 * @param string|null $key The first-level key to look up in $params.
 * @param string|int|null $subKey Optional second-level key to look up under $params[$key].
 * @param bool $sanitize If true, apply HTML escaping (via sanitize) to the returned value when it is a string.
 * @param mixed|null $default Value to return when the requested key/subkey is missing or holds an empty string or null.
 * @return mixed The retrieved (and optionally sanitized) value, or $default when no valid value is found.
 */
function getParamValue(
    $params,
    string|null $key = null,
    string|int|null $subKey = null,
    bool $sanitize = false,
    mixed $default = null,
): mixed {
    // If not an array, return directly (optionally sanitize)
    if (! is_array($params) || $key === null) {
        return $sanitize ? sanitize($params) : $params;
    }

    // Handle nested parameter (with subkey)
    if ($subKey !== null && isset($params[$key][$subKey]) && ($params[$key][$subKey] !== '' && $params[$key][$subKey] !== null)) {
        return $sanitize ? sanitize($params[$key][$subKey]) : $params[$key][$subKey];
    }

    // Handle first-level parameter
    if (isset($params[$key]) && ($params[$key] !== '' && $params[$key] !== null)) {
        return $sanitize ? sanitize($params[$key]) : $params[$key];
    }

    return $default;
}

/**
 * Sanitize a value using htmlspecialchars
 * PS: the htmlspecialchars function is used to keep the same behavior as the original code
 *
 * @param mixed $value
 * @return mixed
 */
function sanitize(mixed $value): mixed
{
    return is_string($value)
        ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        : $value;
}
