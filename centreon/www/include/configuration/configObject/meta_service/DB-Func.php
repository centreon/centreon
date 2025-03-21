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

/**
 * Helper function to generate a bind array.
 *
 * @param mixed $value
 * @param int $targetParamType
 * @return array<mixed, int>
 */
function bind($value, $targetParamType) {
    if ($value === null || $value === '') {
        return [null, PDO::PARAM_NULL];
    }
    return [$value, $targetParamType];
}

/**
 * Check if a meta service exists for a given name
 *
 * @param string $name
 * @return bool
 */
function testExistence($name = null)
{
    global $pearDB, $form;
    $metaIdFromForm = $form ? $form->getSubmitValue('meta_id') : null;
    $query = "SELECT meta_id FROM meta_service WHERE meta_name = :meta_name";
    $meta = [];
    $rowCount = 0;
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':meta_name' => bind(htmlentities($name, ENT_QUOTES, "UTF-8"), PDO::PARAM_STR)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
        $meta = $pearDB->fetch($statement);
        $rowCount = $statement->rowCount();
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while executing testExistence',
            [
                'query' => $query,
                'metaName' => $name,
                'exception' => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
    if ($rowCount >= 1 && $meta["meta_id"] == $metaIdFromForm) {
        return true;
    } elseif ($rowCount >= 1 && $meta["meta_id"] != $metaIdFromForm) {
        return false;
    } else {
        return true;
    }
}

/**
 * Enable a meta service in the DB
 *
 * @param int $metaId
 * @return void
 */
function enableMetaServiceInDB($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $pearDB;
    $query = "UPDATE meta_service SET meta_activate = '1' WHERE meta_id = :meta_id";
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':meta_id' => bind($metaId, PDO::PARAM_INT)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while enabling meta_service',
            [
                'query' => $query,
                'metaId' => $metaId,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
}

/**
 * Disable a meta service in the DB
 *
 * @param int $metaId
 * @return void
 */
function disableMetaServiceInDB($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $pearDB;
    $query = "UPDATE meta_service SET meta_activate = '0' WHERE meta_id = :meta_id";
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':meta_id' => bind($metaId, PDO::PARAM_INT)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error while disabling meta_service',
            [
                'query' => $query,
                'metaId' => $metaId,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
}

/**
 * Remove dependency relation if it is the last one
 *
 * @param int $serviceId
 */
function removeRelationLastMetaServiceDependency(int $serviceId): void
{
    global $pearDB;
    $query = <<<SQL
            SELECT COUNT(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id
            FROM dependency_metaserviceParent_relation
            WHERE dependency_dep_id = (
                SELECT dependency_dep_id
                FROM dependency_metaserviceParent_relation
                WHERE meta_service_meta_id = :serviceId
            )
            GROUP BY dependency_dep_id
        SQL;
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':serviceId' => bind($serviceId, PDO::PARAM_INT)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
        $result = $pearDB->fetch($statement);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error in removeRelationLastMetaServiceDependency',
            [
                'query' => $query,
                'serviceId' => $serviceId,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $queryDel = "DELETE FROM dependency WHERE dep_id = :dep_id";
        try {
            $statementDel = $pearDB->prepareQuery($queryDel);
            $bindParamsDel = [
                ':dep_id' => bind($result['id'], PDO::PARAM_INT)
            ];
            $pearDB->executePreparedQuery($statementDel, $bindParamsDel, true);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting dependency',
                [
                    'query' => $queryDel,
                    'dep_id' => $result['id'],
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
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
        $query = "DELETE FROM meta_service WHERE meta_id = :meta_id";
        try {
            $statement = $pearDB->prepareQuery($query);
            $bindParams = [
                ':meta_id' => bind($metaId, PDO::PARAM_INT)
            ];
            $pearDB->executePreparedQuery($statement, $bindParams, true);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting meta_service',
                [
                    'query' => $query,
                    'meta_id' => $metaId,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
                    ],
                ]
            );
        }
        $query2 = "DELETE FROM service WHERE service_description = :service_description AND service_register = '2'";
        try {
            $statement2 = $pearDB->prepareQuery($query2);
            $bindParams2 = [
                ':service_description' => bind('meta_' . $metaId, PDO::PARAM_STR)
            ];
            $pearDB->executePreparedQuery($statement2, $bindParams2, true);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting service for meta_service',
                [
                    'query' => $query2,
                    'serviceDescription' => 'meta_' . $metaId,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
                    ],
                ]
            );
        }
    }
}

/**
 * Enable a metric in the DB
 *
 * @param int $msrId
 * @return void
 */
function enableMetricInDB($msrId = null)
{
    if (!$msrId) {
        return;
    }
    global $pearDB;
    $query = "UPDATE meta_service_relation SET activate = '1' WHERE msr_id = :msr_id";
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':msr_id' => bind($msrId, PDO::PARAM_INT)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error enabling metric',
            [
                'query' => $query,
                'msrId' => $msrId,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
}

/**
 * Disable a metric in the DB
 *
 * @param int $msrId
 * @return void
 */
function disableMetricInDB($msrId = null)
{
    if (!$msrId) {
        return;
    }
    global $pearDB;
    $query = "UPDATE meta_service_relation SET activate = '0' WHERE msr_id = :msr_id";
    try {
        $statement = $pearDB->prepareQuery($query);
        $bindParams = [
            ':msr_id' => bind($msrId, PDO::PARAM_INT)
        ];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error disabling metric',
            [
                'query' => $query,
                'msr_id' => $msrId,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
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
        $query = "DELETE FROM meta_service_relation WHERE msr_id = :msr_id";
        try {
            $statement = $pearDB->prepareQuery($query);
            $bindParams = [
                ':msr_id' => bind($msrId, PDO::PARAM_INT)
            ];
            $pearDB->executePreparedQuery($statement, $bindParams, true);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error deleting metric',
                [
                    'query' => $query,
                    'msrId' => $msrId,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
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
        $query = "SELECT * FROM meta_service WHERE meta_id = :meta_id LIMIT 1";
        $row = null;
        try {
            $statement = $pearDB->prepareQuery($query);
            $bindParams = [
                ':meta_id' => bind($metaId, PDO::PARAM_INT)
            ];
            $pearDB->executePreparedQuery($statement, $bindParams, true);
            $row = $pearDB->fetch($statement);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching meta_service for duplication',
                [
                    'query' => $query,
                    'metaId' => $metaId,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
                    ],
                ]
            );
        }
        if (!$row) {
            continue;
        }
        $row["meta_id"] = null;
        # Loop on the number of MetaService we want to duplicate
        for ($i = 1; $i <= $nbrDup[$metaId]; $i++) {
            $metaName = $row["meta_name"] . "_" . $i;
            $row["meta_name"] = $metaName;
            $columns = array_keys($row);
            $columnsList = implode(", ", $columns);
            $placeholders = ":" . implode(", :", $columns);
            $insertQuery = "INSERT INTO meta_service ($columnsList) VALUES ($placeholders)";
            try {
                if (! testExistence($metaName)) {
                    continue;
                }
                $statementInsert = $pearDB->prepareQuery($insertQuery);
                $bindParamsInsert = [];
                foreach ($row as $column => $value) {
                    $bindParamsInsert[":$column"] = bind($value, PDO::PARAM_STR);
                }
                $pearDB->executePreparedQuery($statementInsert, $bindParamsInsert, true);
                $newMetaId = $pearDB->lastInsertId();
                if ($newMetaId) {
                    $metaObj = new CentreonMeta($pearDB);
                    $metaObj->insertVirtualService($newMetaId, addslashes($metaName));

                    // Duplicate contacts
                    $queryContacts = "SELECT DISTINCT contact_id FROM meta_contact WHERE meta_id = :meta_id";
                    $statementContacts = $pearDB->prepareQuery($queryContacts);
                    $bindParamsContacts = [
                        ':meta_id' => bind($metaId, PDO::PARAM_INT)
                    ];
                    $pearDB->executePreparedQuery($statementContacts, $bindParamsContacts, true);
                    $contacts = $pearDB->fetchAll($statementContacts);
                    foreach ($contacts as $contact) {
                        $queryInsertContact = "INSERT INTO meta_contact (meta_id, contact_id) VALUES (:meta_id, :contact_id)";
                        $statementInsertContact = $pearDB->prepareQuery($queryInsertContact);
                        $bindParamsInsertContact = [
                            ':meta_id'    => bind((int) $newMetaId, PDO::PARAM_INT),
                            ':contact_id' => bind((int) $contact["contact_id"], PDO::PARAM_INT)
                        ];
                        $pearDB->executePreparedQuery($statementInsertContact, $bindParamsInsertContact, true);
                    }

                    // Duplicate contactgroups
                    $queryCG = "SELECT DISTINCT cg_cg_id FROM meta_contactgroup_relation WHERE meta_id = :meta_id";
                    $statementCG = $pearDB->prepareQuery($queryCG);
                    $bindParamsCG = [
                        ':meta_id' => bind($metaId, PDO::PARAM_INT)
                    ];
                    $pearDB->executePreparedQuery($statementCG, $bindParamsCG, true);
                    $cgroups = $pearDB->fetchAll($statementCG);
                    foreach ($cgroups as $cg) {
                        $queryInsertCG = "INSERT INTO meta_contactgroup_relation (meta_id, cg_cg_id) VALUES (:meta_id, :cg_cg_id)";
                        $statementInsertCG = $pearDB->prepareQuery($queryInsertCG);
                        $bindParamsInsertCG = [
                            ':meta_id'   => bind((int) $newMetaId, PDO::PARAM_INT),
                            ':cg_cg_id'  => bind((int) $cg["cg_cg_id"], PDO::PARAM_INT)
                        ];
                        $pearDB->executePreparedQuery($statementInsertCG, $bindParamsInsertCG, true);
                    }

                    // Duplicate metrics
                    $queryMetric = "SELECT * FROM meta_service_relation WHERE meta_id = :meta_id";
                    $statementMetric = $pearDB->prepareQuery($queryMetric);
                    $bindParamsMetric = [
                        ':meta_id' => bind($metaId, PDO::PARAM_INT)
                    ];
                    $pearDB->executePreparedQuery($statementMetric, $bindParamsMetric, true);
                    $metrics = $pearDB->fetchAll($statementMetric);
                    foreach ($metrics as $metric) {
                        $metric["msr_id"] = null;
                        $metric["meta_id"] = $newMetaId;
                        $cols = array_keys($metric);
                        $colsList = implode(", ", $cols);
                        $placeholdersMetric = ":" . implode(", :", $cols);
                        $insertMetricQuery = "INSERT INTO meta_service_relation ($colsList) VALUES ($placeholdersMetric)";
                        $statementInsertMetric = $pearDB->prepareQuery($insertMetricQuery);
                        $bindParamsMetricInsert = [];
                        foreach ($metric as $column => $value) {
                            $bindParamsMetricInsert[":$column"] = bind($value, PDO::PARAM_STR);
                        }
                        $pearDB->executePreparedQuery($statementInsertMetric, $bindParamsMetricInsert, true);
                    }
                }
            } catch (CentreonDbException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error duplicating meta_service',
                    [
                        'metaId' => $metaId,
                        'metaName' => $metaName,
                        'exception'    => [
                            'message' => $exception->getMessage(),
                            'trace'   => $exception->getTrace()
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
 * @param int $metaId
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
    // Foreach Meta Service
    foreach ($metrics as $msrId => $value) {
        $query = "SELECT * FROM meta_service_relation WHERE msr_id = :msr_id LIMIT 1";
        try {
            $statement = $pearDB->prepareQuery($query);
            $bindParams = [':msr_id' => bind($msrId, PDO::PARAM_INT)];
            $pearDB->executePreparedQuery($statement, $bindParams, true);
            $row = $pearDB->fetch($statement);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error fetching metric for duplication',
                [
                    'msrId'       => $msrId,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
                    ],
                ]
            );
            continue;
        }
        if (!$row) {
            continue;
        }
        $row["msr_id"] = null;
        // Loop on the number of Metric we want to duplicate
        for ($i = 1; $i <= $nbrDup[$msrId]; $i++) {
            $columns = array_keys($row);
            $columnsList = implode(", ", $columns);
            $placeholders = ":" . implode(", :", $columns);
            $insertQuery = "INSERT INTO meta_service_relation ($columnsList) VALUES ($placeholders)";
            try {
                $statementInsert = $pearDB->prepareQuery($insertQuery);
                $bindParamsInsert = [];
                foreach ($row as $col => $val) {
                    $bindParamsInsert[":$col"] = bind($val, PDO::PARAM_STR);
                }
                $pearDB->executePreparedQuery($statementInsert, $bindParamsInsert, true);
            } catch (CentreonDbException $exception) {
                CentreonLog::create()->error(
                    CentreonLog::TYPE_SQL,
                    'Error inserting duplicated metric',
                    [
                        'originalMsrId'   => $msrId,
                        'duplicationIndex' => $i,
                        'exception'    => [
                            'message' => $exception->getMessage(),
                            'trace'   => $exception->getTrace()
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
    $query = "SELECT host_id FROM host WHERE host_register = '2' AND host_name = '_Module_Meta'";
    $statement = $pearDB->executeQuery($query);
    if (!$statement->rowCount()) {
        $queryInsert = "INSERT INTO host (host_name, host_register) VALUES ('_Module_Meta', '2')";
        $pearDB->executeQuery($queryInsert);
        $queryLink = <<<SQL
                INSERT INTO ns_host_relation (nagios_server_id, host_host_id)
                VALUES (
                    (SELECT id FROM nagios_server WHERE localhost = '1'),
                    (SELECT host_id FROM host WHERE host_name = '_Module_Meta')
                )
                ON DUPLICATE KEY UPDATE nagios_server_id = (SELECT id FROM nagios_server WHERE localhost = '1')
            SQL;
        $pearDB->executeQuery($queryLink);
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
    $query = <<<SQL
            INSERT INTO meta_service (
                meta_name, meta_display, check_period, max_check_attempts, normal_check_interval, retry_check_interval,
                notification_interval, notification_period, notification_options, notifications_enabled, calcul_type,
                data_source_type, meta_select_mode, regexp_str, metric, warning, critical, graph_id, meta_comment, geo_coords, meta_activate
            ) VALUES (
                :meta_name, :meta_display, :check_period, :max_check_attempts, :normal_check_interval, :retry_check_interval,
                :notification_interval, :notification_period, :notification_options, :notifications_enabled, :calcul_type,
                :data_source_type, :meta_select_mode, :regexp_str, :metric, :warning, :critical, :graph_id, :meta_comment, :geo_coords, :meta_activate
            )
        SQL;
    $params = [
        ':meta_name' => bind(isset($ret["meta_name"]) ? htmlentities($ret["meta_name"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':meta_display' => bind(isset($ret["meta_display"]) ? htmlentities($ret["meta_display"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':check_period' => bind($ret["check_period"] ?? null, PDO::PARAM_STR),
        ':max_check_attempts' => bind($ret["max_check_attempts"] ?? null, PDO::PARAM_INT),
        ':normal_check_interval' => bind($ret["normal_check_interval"] ?? null, PDO::PARAM_STR),
        ':retry_check_interval' => bind($ret["retry_check_interval"] ?? null, PDO::PARAM_STR),
        ':notification_interval' => bind($ret["notification_interval"] ?? null, PDO::PARAM_STR),
        ':notification_period' => bind($ret["notification_period"] ?? null, PDO::PARAM_STR),
        ':notification_options' => bind(isset($ret["ms_notifOpts"]) ? implode(",", array_keys($ret["ms_notifOpts"])) : null, PDO::PARAM_STR),
        ':notifications_enabled' => bind((isset($ret["notifications_enabled"]["notifications_enabled"]) && $ret["notifications_enabled"]["notifications_enabled"] != '2') ? (int) $ret["notifications_enabled"]["notifications_enabled"] : '2', PDO::PARAM_STR),
        ':calcul_type' => bind($ret["calcul_type"] ?? null, PDO::PARAM_STR),
        ':data_source_type' => bind((int)$ret["data_source_type"], PDO::PARAM_INT),
        ':meta_select_mode' => bind($ret["meta_select_mode"]["meta_select_mode"] ?? null, PDO::PARAM_STR),
        ':regexp_str' => bind(isset($ret["regexp_str"]) ? htmlentities($ret["regexp_str"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':metric' => bind(isset($ret["metric"]) ? htmlentities($ret["metric"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':warning' => bind(isset($ret["warning"]) ? htmlentities($ret["warning"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':critical' => bind(isset($ret["critical"]) ? htmlentities($ret["critical"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':graph_id' => bind($ret["graph_id"] ?? null, PDO::PARAM_STR),
        ':meta_comment' => bind(isset($ret["meta_comment"]) ? htmlentities($ret["meta_comment"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':geo_coords' => bind(isset($ret["geo_coords"]) ? htmlentities($ret["geo_coords"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':meta_activate' => bind(isset($ret["meta_activate"]["meta_activate"]) ? $ret["meta_activate"]["meta_activate"] : null, PDO::PARAM_STR),
    ];
    try {
        $statement = $pearDB->prepareQuery($query);
        $pearDB->executePreparedQuery($statement, $params, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting meta_service',
            [
                'metaName'    => $ret["meta_name"] ?? null,
                'query'        => $query,
                'params'       => $params,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
    $metaId = $pearDB->lastInsertId();
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
 * @param int $metaId
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
    $query = <<<SQL
            UPDATE meta_service SET
            meta_name = :meta_name,
            meta_display = :meta_display,
            check_period = :check_period,
            max_check_attempts = :max_check_attempts,
            normal_check_interval = :normal_check_interval,
            retry_check_interval = :retry_check_interval,
            notification_interval = :notification_interval,
            notification_period = :notification_period,
            notification_options = :notification_options,
            notifications_enabled = :notifications_enabled,
            calcul_type = :calcul_type,
            data_source_type = :data_source_type,
            meta_select_mode = :meta_select_mode,
            regexp_str = :regexp_str,
            metric = :metric,
            warning = :warning,
            critical = :critical,
            graph_id = :graph_id,
            meta_comment = :meta_comment,
            geo_coords = :geo_coords,
            meta_activate = :meta_activate
            WHERE meta_id = :meta_id
        SQL;
    $params = [
        ':meta_name'             => bind(isset($ret["meta_name"]) ? htmlentities($ret["meta_name"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':meta_display'          => bind(isset($ret["meta_display"]) ? htmlentities($ret["meta_display"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':check_period'          => bind($ret["check_period"] ?? null, PDO::PARAM_STR),
        ':max_check_attempts'    => bind($ret["max_check_attempts"] ?? null, PDO::PARAM_INT),
        ':normal_check_interval' => bind($ret["normal_check_interval"] ?? null, PDO::PARAM_STR),
        ':retry_check_interval'  => bind($ret["retry_check_interval"] ?? null, PDO::PARAM_STR),
        ':notification_interval' => bind($ret["notification_interval"] ?? null, PDO::PARAM_STR),
        ':notification_period'   => bind($ret["notification_period"] ?? null, PDO::PARAM_STR),
        ':notification_options'  => bind(isset($ret["ms_notifOpts"]) ? implode(",", array_keys($ret["ms_notifOpts"])) : null, PDO::PARAM_STR),
        ':notifications_enabled' => bind((isset($ret["notifications_enabled"]["notifications_enabled"]) && $ret["notifications_enabled"]["notifications_enabled"] != '2') ? (int) $ret["notifications_enabled"]["notifications_enabled"] : '2', PDO::PARAM_STR),
        ':calcul_type'           => bind($ret["calcul_type"] ?? null, PDO::PARAM_STR),
        ':data_source_type'      => bind($ret["data_source_type"] ?? 0, PDO::PARAM_INT),
        ':meta_select_mode'      => bind($ret["meta_select_mode"]["meta_select_mode"] ?? null, PDO::PARAM_STR),
        ':regexp_str'            => bind(isset($ret["regexp_str"]) ? htmlentities($ret["regexp_str"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':metric'                => bind(isset($ret["metric"]) ? htmlentities($ret["metric"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':warning'               => bind(isset($ret["warning"]) ? htmlentities($ret["warning"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':critical'              => bind(isset($ret["critical"]) ? htmlentities($ret["critical"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':graph_id'              => bind($ret["graph_id"] ?? null, PDO::PARAM_STR),
        ':meta_comment'          => bind(isset($ret["meta_comment"]) ? htmlentities($ret["meta_comment"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':geo_coords'            => bind(isset($ret["geo_coords"]) ? htmlentities($ret["geo_coords"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':meta_activate'         => bind(isset($ret["meta_activate"]["meta_activate"]) ? $ret["meta_activate"]["meta_activate"] : null, PDO::PARAM_STR),
        ':meta_id'               => bind($metaId, PDO::PARAM_INT),
    ];
    try {
        $statement = $pearDB->prepareQuery($query);
        $pearDB->executePreparedQuery($statement, $params, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating meta_service (updateMetaService)',
            [
                'metaId'      => $metaId,
                'query'        => $query,
                'params'       => $params,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
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
    $queryPurge = "DELETE FROM meta_contact WHERE meta_id = :meta_id";
    try {
        $statement = $pearDB->prepareQuery($queryPurge);
        $bindParams = [':meta_id' => bind($metaId, PDO::PARAM_INT)];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error purging meta_contact',
            [
                'metaId'      => $metaId,
                'query'        => $queryPurge,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
    $ret = CentreonUtils::mergeWithInitialValues($form, 'ms_cs');
    if (count($ret)) {
        // Build a single INSERT query with multiple values
        $values = [];
        $bindParams = [];
        foreach ($ret as $key => $contactId) {
            $values[] = "(:metaId_$key, :contactId_$key)";
            $bindParams[":metaId_$key"] = bind((int)$metaId, PDO::PARAM_INT);
            $bindParams[":contactId_$key"] = bind((int)$contactId, PDO::PARAM_INT);
        }
        $queryAddRelation = "INSERT INTO meta_contact (meta_id, contact_id) VALUES " . implode(", ", $values);
        $statement = $pearDB->prepareQuery($queryAddRelation);
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    }
}

/**
 * Update meta service contact group relations
 *
 * @param int $metaId
 * @return void
 */
function updateMetaServiceContactGroup($metaId = null)
{
    if (!$metaId) {
        return;
    }
    global $form, $pearDB;
    $queryDelete = "DELETE FROM meta_contactgroup_relation WHERE meta_id = :meta_id";
    try {
        $statement = $pearDB->prepareQuery($queryDelete);
        $bindParams = [':meta_id' => bind($metaId, PDO::PARAM_INT)];
        $pearDB->executePreparedQuery($statement, $bindParams, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error deleting meta_contactgroup_relation',
            [
                'metaId'      => $metaId,
                'query'        => $queryDelete,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
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
        $queryInsert = "INSERT INTO meta_contactgroup_relation (meta_id, cg_cg_id) VALUES (:meta_id, :cg_cg_id)";
        $bindParams = [];
        try {
            $statementInsert = $pearDB->prepareQuery($queryInsert);
            $bindParams[':meta_id'] = bind((int)$metaId, PDO::PARAM_INT);
            $bindParams[':cg_cg_id'] = bind((int)$group, PDO::PARAM_INT);
            $pearDB->executePreparedQuery($statementInsert, $bindParams, true);
        } catch (CentreonDbException $exception) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_SQL,
                'Error inserting meta_contactgroup_relation',
                [
                    'metaId'   => $metaId,
                    'group_id'  => $group,
                    'query'     => $queryInsert,
                    'exception'    => [
                        'message' => $exception->getMessage(),
                        'trace'   => $exception->getTrace()
                    ],
                ]
            );
        }
    }
}

/**
 * Update metric – simply calls updateMetric
 *
 * @param int $msrId
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
    $query = <<<SQL
            INSERT INTO meta_service_relation (meta_id, host_id, metric_id, msr_comment, activate)
            VALUES (:meta_id, :host_id, :metric_id, :msr_comment, :activate)
        SQL;
    $params = [
        ':meta_id'     => bind($ret["meta_id"] ?? null, PDO::PARAM_INT),
        ':host_id'     => bind($ret["host_id"] ?? null, PDO::PARAM_INT),
        ':metric_id'   => bind(isset($ret["metric_sel"][1]) ? $ret["metric_sel"][1] : null, PDO::PARAM_INT),
        ':msr_comment' => bind(isset($ret["msr_comment"]) ? htmlentities($ret["msr_comment"]) : null, PDO::PARAM_STR),
        ':activate'    => bind(isset($ret["activate"]["activate"]) ? $ret["activate"]["activate"] : null, PDO::PARAM_STR),
    ];
    try {
        $statement = $pearDB->prepareQuery($query);
        $pearDB->executePreparedQuery($statement, $params, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error inserting metric',
            [
                'query'  => $query,
                'params' => $params,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
    $msrId = $pearDB->lastInsertId();

    return $msrId;
}

/**
 * Update a metric
 *
 * @param int $msrId
 * @return void
 */
function updateMetric($msrId = null)
{
    if (!$msrId) {
        return;
    }
    global $form, $pearDB;
    $ret = $form->getSubmitValues();
    $query = <<<SQL
            UPDATE meta_service_relation SET
                meta_id = :meta_id,
                host_id = :host_id,
                metric_id = :metric_id,
                msr_comment = :msr_comment,
                activate = :activate
            WHERE msr_id = :msr_id
        SQL;
    $params = [
        ':meta_id'     => bind($ret["meta_id"] ?? null, PDO::PARAM_INT),
        ':host_id'     => bind($ret["host_id"] ?? null, PDO::PARAM_INT),
        ':metric_id'   => bind(isset($ret["metric_sel"][1]) ? $ret["metric_sel"][1] : null, PDO::PARAM_INT),
        ':msr_comment' => bind(isset($ret["msr_comment"]) ? htmlentities($ret["msr_comment"], ENT_QUOTES, "UTF-8") : null, PDO::PARAM_STR),
        ':activate'    => bind(isset($ret["activate"]["activate"]) ? $ret["activate"]["activate"] : null, PDO::PARAM_STR),
        ':msr_id'      => bind($msrId, PDO::PARAM_INT),
    ];
    try {
        $statement = $pearDB->prepareQuery($query);
        $pearDB->executePreparedQuery($statement, $params, true);
    } catch (CentreonDbException $exception) {
        CentreonLog::create()->error(
            CentreonLog::TYPE_SQL,
            'Error updating metric',
            [
                'msrId'       => $msrId,
                'query'        => $query,
                'params'       => $params,
                'exception'    => [
                    'message' => $exception->getMessage(),
                    'trace'   => $exception->getTrace()
                ],
            ]
        );
    }
}