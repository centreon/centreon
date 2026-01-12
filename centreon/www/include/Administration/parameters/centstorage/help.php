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

$help = [];

/**
 * Engine Status
 */
$help['tip_path_to_rrdtool_database_for_metrics'] = dgettext('help', 'Path to RRDTool database for graphs of metrics.');
$help['tip_path_to_rrdtool_database_for_status'] = dgettext('help', 'Path to RRDTool database for graphs of status.');
$help['tip_path_to_rrdtool_database_for_nagios_statistics'] = dgettext(
    'help',
    'Path to RRDTool database for graphs of monitoring engine stats.'
);

/**
 * Retention durations
 */
$help['tip_rrdtool_database_size'] = dgettext('help', 'RRDTool database size (in days).');
$help['tip_retention_duration_for_data_in_mysql'] = dgettext(
    'help',
    'Duration of retention regarding performance data stored in database. 0 means that no retention will be applied.'
);
$help['tip_retention_duration_for_data_in_downtimes'] = dgettext(
    'help',
    'Duration of retention regarding downtimes stored in database. 0 means that no retention will be applied.'
);
$help['tip_retention_duration_for_data_in_comments'] = dgettext(
    'help',
    'Duration of retention regarding comments stored in database. 0 means that no retention will be applied.'
);

/**
 * Logs Integration Properties
 */
$help['tip_logs_retention_duration'] = dgettext(
    'help',
    'Retention duration of logs. 0 means that no retention will be applied.'
);

/**
 * Reporting Dashboard
 */
$help['tip_reporting_retention'] = dgettext(
    'help',
    'Retention duration of reporting data. 0 means that no retention will be applied.'
);

/**
 * Partitioning retention options
 */
$help['tip_partitioning_retention'] = dgettext(
    'help',
    'Retention time for partitioned tables (data_bin, logs, log_archive_host, log_archive_service), by default 365 days.'
);
$help['tip_partitioning_retention_forward'] = dgettext(
    'help',
    'number of partitions created in advance to prevent issues, by default 10 days.'
);
$help['tip_partitioning_backup_directory'] = dgettext(
    'help',
    'Backup directory to store partition, by default /var/cache/centreon/backup.'
);

/**
 * Audit Logs
 */
$help['tip_audit_log_option'] = dgettext('help', 'Enable/Disable logging of all modifications in Centreon');
