<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonContactgroup.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonMeta.class.php';

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\Connection\Exception\ConnectionException;

/**
 * Check if a meta service exists for a given name
 *
 * @param string|null $name
 * @return bool
 */
function testExistence($name = null)
{
    global $pearDB, $form;
    $metaIdFromForm = $form ? $form->getSubmitValue('meta_id') : null;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->select("meta_id")
        ->from("meta_service")
        ->where("meta_name = :meta_name")
        ->getQuery();
    try {
        $meta = $pearDB->fetchAssociative($query, QueryParameters::create([
            QueryParameter::string('meta_name', getParamValue($name, sanitize: true))
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while executing testExistence',
            [
                'query' => $query,
                'metaName' => $name,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
        $meta = false;
    }
    if ($meta && isset($meta["meta_id"])) {
        return ($meta["meta_id"] == $metaIdFromForm);
    }

    return true;
}

/**
 * Enable a meta service in the DB
 *
 * @param int|null $metaId
 * @return void
 */
function enableMetaServiceInDB($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update("meta_service")
        ->set("meta_activate", "'1'")
        ->where("meta_id = :meta_id")
        ->getQuery();
    try {
        $pearDB->executeStatement($query, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while enabling meta_service',
            [
                'query' => $query,
                'metaId' => $metaId,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
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
    if (!$metaId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update("meta_service")
        ->set("meta_activate", "'0'")
        ->where("meta_id = :meta_id")
        ->getQuery();
    try {
        $pearDB->executeStatement($query, QueryParameters::create([
            QueryParameter::int('meta_id', (int)$metaId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while disabling meta_service',
            [
                'query'  => $query,
                'metaId' => $metaId,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
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
    $subQuery = $subQb->select("dependency_dep_id")
                    ->from("dependency_metaserviceParent_relation")
                    ->where("meta_service_meta_id = :serviceId")
                    ->getQuery();

    $qb = $pearDB->createQueryBuilder();
    $query = $qb->select("COUNT(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id")
                ->from("dependency_metaserviceParent_relation")
                ->where("dependency_dep_id = (" . $subQuery . ")")
                ->groupBy("dependency_dep_id")
                ->getQuery();
    try {
        $result = $pearDB->fetchAssociative($query, QueryParameters::create([
            QueryParameter::int('serviceId', $serviceId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error in removeRelationLastMetaServiceDependency',
            [
                'query' => $query,
                'serviceId' => $serviceId,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
        return;
    }
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $qbDel = $pearDB->createQueryBuilder();
        $queryDel = $qbDel->delete("dependency")
                  ->where("dep_id = :dep_id")
                  ->getQuery();
        try {
            $pearDB->executeStatement($queryDel, QueryParameters::create([
                QueryParameter::int('dep_id', (int)$result['id'])
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting dependency',
                [
                    'query' => $queryDel,
                    'depId' => $result['id'],
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace(),
                    ],
                ]
            );
        }
    }
}

/**
 * Delete meta service(s) and corresponding service entries
 *
 * @param array<mixed> $metas
 * @return void
 */
function deleteMetaServiceInDB($metas = [])
{
    global $pearDB;
    foreach ($metas as $metaId => $value) {
        removeRelationLastMetaServiceDependency((int)$metaId);
        $qb = $pearDB->createQueryBuilder();
        $query = $qb->delete("meta_service")
                    ->where("meta_id = :meta_id")
                    ->getQuery();
        try {
            $pearDB->executeStatement($query, QueryParameters::create([
                QueryParameter::int('meta_id', (int) $metaId)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting meta_service',
                [
                    'query' => $query,
                    'meta_id' => $metaId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace(),
                    ],
                ]
            );
        }
        $qb2 = $pearDB->createQueryBuilder();
        $query2 = $qb2->delete("service")
                     ->where("service_description = :service_description")
                     ->andWhere("service_register = '2'")
                     ->getQuery();
        try {
            $pearDB->executeStatement($query2, QueryParameters::create([
                QueryParameter::string('service_description', 'meta_' . $metaId)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting service for meta_service',
                [
                    'query' => $query2,
                    'serviceDescription' => 'meta_' . $metaId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace(),
                    ],
                ]
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
    if (!$msrId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update("meta_service_relation")
                ->set("activate", "'1'")
                ->where("msr_id = :msr_id")
                ->getQuery();
    try {
        $pearDB->executeStatement($query, QueryParameters::create([
            QueryParameter::int('msr_id', (int)$msrId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error enabling metric',
            [
                'query' => $query,
                'msrId' => $msrId,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
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
    if (!$msrId) {
        return;
    }
    global $pearDB;
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update("meta_service_relation")
                ->set("activate", "'0'")
                ->where("msr_id = :msr_id")
                ->getQuery();
    try {
        $pearDB->executeStatement($query, QueryParameters::create([
            QueryParameter::int('msr_id', (int)$msrId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error disabling metric',
            [
                'query' => $query,
                'msrId' => $msrId,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
}

/**
 * Delete metric(s) from the DB
 *
 * @param array<mixed> $metrics
 * @return void
 */
function deleteMetricInDB($metrics = [])
{
    global $pearDB;
    foreach ($metrics as $msrId => $value) {
        $qb = $pearDB->createQueryBuilder();
        $query = $qb->delete("meta_service_relation")
                    ->where("msr_id = :msr_id")
                    ->getQuery();
        try {
            $pearDB->executeStatement($query, QueryParameters::create([
                QueryParameter::int('msr_id', (int)$msrId)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting metric',
                [
                    'query' => $query,
                    'msrId' => $msrId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace(),
                    ],
                ]
            );
        }
    }
}

/**
 * Duplicate meta services
 *
 * @param array<int> $metas   Array of meta_ids to duplicate
 * @param array<int> $nbrDup  Array of duplication counts indexed by meta_id
 * @return void
 */
function multipleMetaServiceInDB($metas = [], $nbrDup = [])
{
    global $pearDB;
    foreach ($metas as $metaId => $value) {
        $qbSelect = $pearDB->createQueryBuilder();
        $query = $qbSelect->select("*")
                          ->from("meta_service")
                          ->where("meta_id = :meta_id")
                          ->limit(1)
                          ->getQuery();
        try {
            $row = $pearDB->fetchAssociative($query, QueryParameters::create([
                QueryParameter::int('meta_id', (int) $metaId)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching meta_service for duplication',
                [
                    'query' => $query,
                    'metaId' => $metaId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ],
                ]
            );
            continue;
        }
        if (!$row) {
            continue;
        }
        $row["meta_id"] = null;
        for ($i = 1; $i <= $nbrDup[$metaId]; $i++) {
            $metaName = $row["meta_name"] . "_" . $i;
            $row["meta_name"] = $metaName;
            $columns = array_keys($row);
            $qbInsert = $pearDB->createQueryBuilder();
            $insertQuery = $qbInsert->insert("meta_service")
                ->values(array_combine($columns, array_map(fn($col) => ':' . $col, $columns)));
            $query = $insertQuery->getQuery();

            try {
                if (! testExistence($metaName)) {
                    continue;
                }
                $params = [];
                foreach ($row as $column => $value) {
                    $params[] = QueryParameter::string($column, $value);
                }
                $pearDB->executeStatement($query, QueryParameters::create($params));
                $newMetaId = $pearDB->getLastInsertId();
                if ($newMetaId) {
                    $metaObj = new CentreonMeta($pearDB);
                    $metaObj->insertVirtualService($newMetaId, addslashes($metaName));

                    // Duplicate contacts
                    $qbContacts = $pearDB->createQueryBuilder();
                    $queryContacts = $qbContacts->select("DISTINCT contact_id")
                        ->from("meta_contact")
                        ->where("meta_id = :meta_id");
                    $query = $queryContacts->getQuery();
                    $contacts = $pearDB->fetchAllAssociative($query, QueryParameters::create([
                        QueryParameter::int('meta_id', (int) $metaId)
                    ]));
                    foreach ($contacts as $contact) {
                        $qbInsertContact = $pearDB->createQueryBuilder();
                        $queryInsertContact = $qbInsertContact->insert("meta_contact")
                            ->values([
                                'meta_id'    => ':meta_id',
                                'contact_id' => ':contact_id'
                            ]);
                        $query = $queryInsertContact->getQuery();
                        $pearDB->executeStatement($query, QueryParameters::create([
                            QueryParameter::int('meta_id', (int) $newMetaId),
                            QueryParameter::int('contact_id', (int) $contact["contact_id"])
                        ]));
                    }

                    // Duplicate contactgroups
                    $qbCG = $pearDB->createQueryBuilder();
                    $queryCG = $qbCG->select("DISTINCT cg_cg_id")
                        ->from("meta_contactgroup_relation")
                        ->where("meta_id = :meta_id");
                    $query = $queryCG->getQuery();
                    $cgroups = $pearDB->fetchAllAssociative($query, QueryParameters::create([
                        QueryParameter::int('meta_id', (int) $metaId)
                    ]));
                    foreach ($cgroups as $cg) {
                        $qbInsertCG = $pearDB->createQueryBuilder();
                        $queryInsertCG = $qbInsertCG->insert("meta_contactgroup_relation")
                            ->values([
                                'meta_id'   => ':meta_id',
                                'cg_cg_id'  => ':cg_cg_id'
                            ]);
                        $query = $queryInsertCG->getQuery();
                        $pearDB->executeStatement($query, QueryParameters::create([
                            QueryParameter::int('meta_id', (int) $newMetaId),
                            QueryParameter::int('cg_cg_id', (int) $cg["cg_cg_id"])
                        ]));
                    }

                    // Duplicate metrics
                    $qbMetric = $pearDB->createQueryBuilder();
                    $queryMetric = $qbMetric->select("*")
                        ->from("meta_service_relation")
                        ->where("meta_id = :meta_id");
                    $query = $queryMetric->getQuery();
                    $metricsRows = $pearDB->fetchAllAssociative($query, QueryParameters::create([
                        QueryParameter::int('meta_id', (int) $metaId)
                    ]));
                    foreach ($metricsRows as $metric) {
                        $metric["msr_id"] = null;
                        $metric["meta_id"] = $newMetaId;
                        $columns = array_keys($metric);
                        $qbInsertMetric = $pearDB->createQueryBuilder();
                        $insertMetricQuery = $qbInsertMetric->insert("meta_service_relation")
                            ->values(array_combine($columns, array_map(fn($col) => ':' . $col, $columns)));
                        $query = $insertMetricQuery->getQuery();
                        // Build parameters for the metric row.
                        $paramsMetric = [];
                        foreach ($metric as $column => $value) {
                            $paramsMetric[] =  QueryParameter::string($column, $value);
                        }
                        $pearDB->executeStatement($query, QueryParameters::create($paramsMetric));
                    }
                }
            } catch (ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error duplicating meta_service',
                    [
                        'metaId' => $metaId,
                        'metaName' => $metaName,
                        'query' => $query ?? '',
                        'exception' => [
                            'message' => $exception->getMessage(),
                            'trace' => $exception->getTrace(),
                        ],
                    ]
                );
            }
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
    if (!$metaId) {
        return;
    }
    updateMetaService($metaId);
    updateMetaServiceContact($metaId);
    updateMetaServiceContactGroup($metaId);
}

/**
 * Insert a new meta service in the DB
 *
 * @return int
 */
function insertMetaServiceInDB()
{
    $metaId = insertMetaService();
    updateMetaServiceContact($metaId);
    updateMetaServiceContactGroup($metaId);
    return $metaId;
}

/**
 * Duplicate metrics: for each metric to duplicate, fetch its row and insert duplicates
 *
 * @param array<int> $metrics
 * @param array<int> $nbrDup
 * @return void
 */
function multipleMetricInDB($metrics = [], $nbrDup = [])
{
    global $pearDB;
    foreach ($metrics as $msrId => $value) {
        $qbSelect = $pearDB->createQueryBuilder();
        $query = $qbSelect->select("*")
                          ->from("meta_service_relation")
                          ->where("msr_id = :msr_id")
                          ->limit(1)
                          ->getQuery();
        try {
            $row = $pearDB->fetchAssociative($query, QueryParameters::create([
                QueryParameter::int('msr_id', (int) $msrId)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching metric for duplication',
                [
                    'msrId' => $msrId,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ],
                ]
            );
            continue;
        }
        if (!$row) {
            continue;
        }
        $row["msr_id"] = null;
        for ($i = 1; $i <= $nbrDup[$msrId]; $i++) {
            $columns = array_keys($row);
            $qbInsert = $pearDB->createQueryBuilder();
            $insertQuery = $qbInsert->insert("meta_service_relation")
                                    ->values(array_combine($columns, array_map(fn($col) => ':' . $col, $columns)))
                                    ->getQuery();
            try {
                $params = [];
                foreach ($row as $column => $val) {
                    $params[] = QueryParameter::string($column, $val);
                }
                $pearDB->executeStatement($insertQuery, QueryParameters::create($params));
            } catch (ConnectionException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error inserting duplicated metric',
                    [
                        'originalMsrId' => $msrId,
                        'duplicationIndex' => $i,
                        'exception' => [
                            'message' => $exception->getMessage(),
                            'trace' => $exception->getTrace(),
                        ],
                    ]
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
    $query = $qbSelect->select("host_id")
                      ->from("host")
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
                'query' => $query,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ],
            ]
        );
        $host = false;
    }
    if (!$host) {
        $qbInsert = $pearDB->createQueryBuilder();
        $queryInsert = $qbInsert->insert("host")
                                ->values([
                                    'host_name' => "'_Module_Meta'",
                                    'host_register' => "'2'"
                                ])
                                ->getQuery();
        try {
            $pearDB->executeStatement($queryInsert);
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error inserting _Module_Meta host',
                [
                    'query' => $queryInsert,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ],
                ]
            );
        }

        // For linking, the subqueries are left as raw SQL for clarity.
        $queryLink = <<<SQL
                INSERT INTO ns_host_relation (nagios_server_id, host_host_id)
                VALUES (
                    (SELECT id FROM nagios_server WHERE localhost = '1'),
                    (SELECT host_id FROM host WHERE host_name = '_Module_Meta')
                )
                ON DUPLICATE KEY UPDATE nagios_server_id = (SELECT id FROM nagios_server WHERE localhost = '1')
            SQL;
        try {
            $pearDB->executeStatement($queryLink);
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error linking _Module_Meta host to Nagios server',
                [
                    'query' => $queryLink,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ],
                ]
            );
        }
    }
}

/**
 * Insert meta service
 *
 * @param array<mixed> $ret
 * @return int
 */
function insertMetaService($ret = [])
{
    global $form, $pearDB, $centreon;
    checkMetaHost();
    if (!count($ret)) {
        $ret = $form->getSubmitValues();
    }
    $qbInsert = $pearDB->createQueryBuilder();
    $query = $qbInsert->insert("meta_service")
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
            'meta_activate' => ':meta_activate'
        ])
        ->getQuery();

    $params = [
        QueryParameter::string('meta_name', getParamValue($ret, "meta_name", sanitize: true)),
        QueryParameter::string('meta_display', getParamValue($ret, "meta_display", sanitize: true)),
        QueryParameter::string('check_period', getParamValue($ret, "check_period")),
        QueryParameter::int('max_check_attempts', getParamValue($ret, "max_check_attempts")),
        QueryParameter::string('normal_check_interval', getParamValue($ret, "normal_check_interval")),
        QueryParameter::string('retry_check_interval', getParamValue($ret, "retry_check_interval")),
        QueryParameter::string('notification_interval', getParamValue($ret, "notification_interval")),
        QueryParameter::string('notification_period', getParamValue($ret, "notification_period")),
        QueryParameter::string('notification_options', isset($ret["ms_notifOpts"]) ? implode(",", array_keys($ret["ms_notifOpts"])) : null),
        QueryParameter::string('notifications_enabled', getParamValue($ret, "notifications_enabled", "notifications_enabled", default: '2')),
        QueryParameter::string('calcul_type', $ret["calcul_type"] ?? null),
        QueryParameter::int('data_source_type', getParamValue($ret, "data_source_type", default: 0)),
        QueryParameter::string('meta_select_mode', getParamValue($ret, "meta_select_mode", "meta_select_mode")),
        QueryParameter::string('regexp_str', getParamValue($ret, "regexp_str", sanitize: true)),
        QueryParameter::string('metric', getParamValue($ret, "metric", sanitize: true)),
        QueryParameter::string('warning', getParamValue($ret, "warning", sanitize: true)),
        QueryParameter::string('critical', getParamValue($ret, "critical", sanitize: true)),
        QueryParameter::string('graph_id', getParamValue($ret, "graph_id")),
        QueryParameter::string('meta_comment', getParamValue($ret, "meta_comment", sanitize: true)),
        QueryParameter::string('geo_coords', getParamValue($ret, "geo_coords", sanitize: true)),
        QueryParameter::string('meta_activate', getParamValue($ret, "meta_activate", "meta_activate")),
    ];
    try {
        $pearDB->executeStatement($query, QueryParameters::create($params));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting meta_service',
            [
                'metaName' => $ret["meta_name"] ?? null,
                'query'    => $query,
                'params'   => $params,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
    $metaId = $pearDB->getLastInsertId();
    if (!$metaId) {
        return 0;
    }
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog("meta", $metaId, addslashes($ret["meta_name"]), "a", $fields);
    $metaObj = new CentreonMeta($pearDB);
    $metaObj->insertVirtualService($metaId, addslashes($ret["meta_name"]));
    return $metaId;
}

/**
 * Update meta service
 *
 * @param int|null $metaId
 * @return void
 */
function updateMetaService($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $form, $pearDB, $centreon;
    checkMetaHost();
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $qb->update("meta_service")
       ->set("meta_name", ":meta_name")
       ->set("meta_display", ":meta_display")
       ->set("check_period", ":check_period")
       ->set("max_check_attempts", ":max_check_attempts")
       ->set("normal_check_interval", ":normal_check_interval")
       ->set("retry_check_interval", ":retry_check_interval")
       ->set("notification_interval", ":notification_interval")
       ->set("notification_period", ":notification_period")
       ->set("notification_options", ":notification_options")
       ->set("notifications_enabled", ":notifications_enabled")
       ->set("calcul_type", ":calcul_type")
       ->set("data_source_type", ":data_source_type")
       ->set("meta_select_mode", ":meta_select_mode")
       ->set("regexp_str", ":regexp_str")
       ->set("metric", ":metric")
       ->set("warning", ":warning")
       ->set("critical", ":critical")
       ->set("graph_id", ":graph_id")
       ->set("meta_comment", ":meta_comment")
       ->set("geo_coords", ":geo_coords")
       ->set("meta_activate", ":meta_activate")
       ->where("meta_id = :meta_id");
    $query = $qb->getQuery();
    $params = [
        QueryParameter::string('meta_name', getParamValue($ret, "meta_name", sanitize: true)),
        QueryParameter::string('meta_display', getParamValue($ret, "meta_display", sanitize: true)),
        QueryParameter::string('check_period', getParamValue($ret, "check_period")),
        QueryParameter::int('max_check_attempts', getParamValue($ret, "max_check_attempts")),
        QueryParameter::string('normal_check_interval', getParamValue($ret, "normal_check_interval")),
        QueryParameter::string('retry_check_interval', getParamValue($ret, "retry_check_interval")),
        QueryParameter::string('notification_interval', getParamValue($ret, "notification_interval")),
        QueryParameter::string('notification_period', getParamValue($ret, "notification_period")),
        QueryParameter::string('notification_options', isset($ret["ms_notifOpts"]) ? implode(",", array_keys($ret["ms_notifOpts"])) : null),
        QueryParameter::string('notifications_enabled', getParamValue($ret, "notifications_enabled", "notifications_enabled", false, '2')),
        QueryParameter::string('calcul_type', $ret["calcul_type"] ?? null),
        QueryParameter::int('data_source_type', getParamValue($ret, "data_source_type", null, false, 0)),
        QueryParameter::string('meta_select_mode', getParamValue($ret, "meta_select_mode", "meta_select_mode")),
        QueryParameter::string('regexp_str', getParamValue($ret, "regexp_str", sanitize: true)),
        QueryParameter::string('metric', getParamValue($ret, "metric", sanitize: true)),
        QueryParameter::string('warning', getParamValue($ret, "warning", sanitize: true)),
        QueryParameter::string('critical', getParamValue($ret, "critical", sanitize: true)),
        QueryParameter::string('graph_id', getParamValue($ret, "graph_id")),
        QueryParameter::string('meta_comment', getParamValue($ret, "meta_comment", sanitize: true)),
        QueryParameter::string('geo_coords', getParamValue($ret, "geo_coords", sanitize: true)),
        QueryParameter::string('meta_activate', getParamValue($ret, "meta_activate", "meta_activate")),
        QueryParameter::int('meta_id', (int) $metaId),
    ];
    try {
        $pearDB->executeStatement($query, QueryParameters::create($params));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating meta_service (updateMetaService)',
            [
                'metaId' => $metaId,
                'query'  => $query,
                'params' => $params,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
    $fields = CentreonLogAction::prepareChanges($ret);
    $centreon->CentreonLogAction->insertLog("meta", $metaId, addslashes($ret["meta_name"]), "c", $fields);
    $metaObj = new CentreonMeta($pearDB);
    $metaObj->insertVirtualService($metaId, addslashes($ret["meta_name"]));
}

/**
 * Update meta service contact relations
 *
 * @param int $metaId
 * @return void
 */
function updateMetaServiceContact($metaId)
{
    if (!$metaId || !is_numeric($metaId)) {
        return;
    }
    global $form, $pearDB;
    $qbDelete = $pearDB->createQueryBuilder();
    $queryPurge = $qbDelete->delete("meta_contact")
                           ->where("meta_id = :meta_id")
                           ->getQuery();
    try {
        $pearDB->executeStatement($queryPurge, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error purging meta_contact',
            [
                'metaId' => $metaId,
                'query'  => $queryPurge,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
    $ret = CentreonUtils::mergeWithInitialValues($form, 'ms_cs');
    if (count($ret)) {
        // Build a single INSERT query with multiple values
        $values = [];
        $params = [];
        foreach ($ret as $key => $contactId) {
            $values[] = "(:metaId_$key, :contactId_$key)";
            $params["metaId_$key"] = QueryParameter::int("metaId_$key", (int) $metaId);
            $params["contactId_$key"] = QueryParameter::int("contactId_$key", (int) $contactId);
        }
        $queryAddRelation = "INSERT INTO meta_contact (meta_id, contact_id) VALUES " . implode(", ", $values);
        try {
            $pearDB->executeStatement($queryAddRelation, QueryParameters::create(array_values($params)));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error inserting meta_contact relations',
                [
                    'metaId' => $metaId,
                    'query' => $queryAddRelation,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace(),
                    ]
                ]
            );
        }
    }
}

/**
 * Update meta service contact group relations
 *
 * @param int|null $metaId
 * @return void
 */
function updateMetaServiceContactGroup($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $form, $pearDB;
    $qbDelete = $pearDB->createQueryBuilder();
    $queryDelete = $qbDelete->delete("meta_contactgroup_relation")
                            ->where("meta_id = :meta_id")
                            ->getQuery();
    try {
        $pearDB->executeStatement($queryDelete, QueryParameters::create([
            QueryParameter::int('meta_id', (int) $metaId)
        ]));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error deleting meta_contactgroup_relation',
            [
                'metaId' => $metaId,
                'query' => $queryDelete,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ],
            ]
        );
        return;
    }
    $ret = CentreonUtils::mergeWithInitialValues($form, 'ms_cgs');
    $cg = new CentreonContactgroup($pearDB);
    foreach ($ret as $group) {
        if (!is_numeric($group)) {
            $res = $cg->insertLdapGroup($group);
            if ($res != 0) {
                $group = $res;
            } else {
                continue;
            }
        }
        $qbInsert = $pearDB->createQueryBuilder();
        $queryInsert = $qbInsert->insert("meta_contactgroup_relation")
                                ->values([
                                    'meta_id' => ':meta_id',
                                    'cg_cg_id' => ':cg_cg_id'
                                ])
                                ->getQuery();
        try {
            $pearDB->executeStatement($queryInsert, QueryParameters::create([
                QueryParameter::int('meta_id', (int) $metaId),
                QueryParameter::int('cg_cg_id', (int) $group)
            ]));
        } catch (ConnectionException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error inserting meta_contactgroup_relation',
                [
                    'metaId' => $metaId,
                    'group_id' => $group,
                    'query' => $queryInsert,
                    'exception' => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace(),
                    ],
                ]
            );
        }
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
    if (!$msrId) {
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
 * Insert a metric
 *
 * @param array<mixed> $ret
 * @return int
 */
function insertMetric($ret = [])
{
    global $form, $pearDB, $centreon;
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->insert("meta_service_relation")
        ->values([
            'meta_id' => ':meta_id',
            'host_id' => ':host_id',
            'metric_id' => ':metric_id',
            'msr_comment' => ':msr_comment',
            'activate' => ':activate'
        ])
        ->getQuery();
    $params = [
        QueryParameter::int('meta_id', getParamValue($ret, "meta_id")),
        QueryParameter::int('host_id', getParamValue($ret, "host_id")),
        QueryParameter::int('metric_id', getParamValue($ret, "metric_sel", 1)),
        QueryParameter::string('msr_comment', getParamValue($ret, "msr_comment", sanitize: true)),
        QueryParameter::string('activate', getParamValue($ret, "activate", "activate")),
    ];
    try {
        $pearDB->executeStatement($query, QueryParameters::create($params));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting metric',
            [
                'query' => $query,
                'params' => $params,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
    $msrId = $pearDB->getLastInsertId();
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
    if (!$msrId) {
        return;
    }
    global $form, $pearDB;
    $ret = $form->getSubmitValues();
    $qb = $pearDB->createQueryBuilder();
    $query = $qb->update("meta_service_relation")
                ->set("meta_id", ":meta_id")
                ->set("host_id", ":host_id")
                ->set("metric_id", ":metric_id")
                ->set("msr_comment", ":msr_comment")
                ->set("activate", ":activate")
                ->where("msr_id = :msr_id")
                ->getQuery();
    $params = [
        QueryParameter::int('meta_id', getParamValue($ret, "meta_id")),
        QueryParameter::int('host_id', getParamValue($ret, "host_id")),
        QueryParameter::int('metric_id', getParamValue($ret,"metric_sel", 1)),
        QueryParameter::string('msr_comment', getParamValue($ret, "msr_comment", sanitize: true)),
        QueryParameter::string('activate', getParamValue($ret, "activate", "activate")),
        QueryParameter::int('msr_id', (int) $msrId),
    ];
    try {
        $pearDB->executeStatement($query, QueryParameters::create($params));
    } catch (ConnectionException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating metric',
            [
                'msrId' => $msrId,
                'query' => $query,
                'params' => $params,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace(),
                ],
            ]
        );
    }
}

/**
 * Retrieve and optionally sanitize a parameter from a (possibly nested) array
 *
 * @param array<mixed>|mixed $params      Main input parameter or array of parameters
 * @param string|null        $key         The first-level key
 * @param string|int|null    $subKey      Optional subkey for nested access
 * @param bool               $sanitize    Whether to sanitize the value using htmlspecialchars
 * @param mixed|null         $default     Default value if key is not found
 *
 * @return mixed
 */
function getParamValue(
    $params,
    string|null $key = null,
    string|int|null $subKey = null,
    bool $sanitize = false,
    mixed $default = null
): mixed {
    // If not an array, return directly (optionally sanitize)
    if (!is_array($params) || !$key) {
        return $sanitize ? sanitize($params) : $params;
    }

    // Handle nested parameter (with subkey)
    if ($subKey !== null && isset($params[$key][$subKey])) {
        return $sanitize ? sanitize($params[$key][$subKey]) : $params[$key][$subKey];
    }

    // Handle first-level parameter
    if (isset($params[$key])) {
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