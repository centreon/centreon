// Extensions manager

const modulesDirectory = '/usr/share/centreon/www/modules';

// Minimal module the Extensions manager can install, update and remove:
// a conf.php (read by ModuleSource) and an upgrade/ directory (read by the
// legacy Upgrader, which fails when it is missing).
const buildWriteModuleCommand = (moduleName: string, version: string): string =>
  [
    `mkdir -p ${modulesDirectory}/${moduleName}/upgrade/${version}`,
    `cat > ${modulesDirectory}/${moduleName}/conf.php <<'EOF'`,
    '<?php',
    '',
    `$module_conf['${moduleName}']['name'] = '${moduleName}';`,
    `$module_conf['${moduleName}']['rname'] = '${moduleName}';`,
    `$module_conf['${moduleName}']['mod_release'] = '${version}';`,
    `$module_conf['${moduleName}']['infos'] = 'Disposable module of the E2E tests';`,
    `$module_conf['${moduleName}']['is_removeable'] = '1';`,
    `$module_conf['${moduleName}']['author'] = 'Centreon QA';`,
    `$module_conf['${moduleName}']['stability'] = 'stable';`,
    `$module_conf['${moduleName}']['svc_tools'] = '0';`,
    `$module_conf['${moduleName}']['host_tools'] = '0';`,
    'EOF',
    `chown -R apache:apache ${modulesDirectory}/${moduleName}`,
    // Let the PHP opcache see the new conf.php (revalidated on timestamp)
    `touch ${modulesDirectory}/${moduleName}/conf.php`
  ].join('\n');

const buildInstalledModuleQuery = (moduleName: string): string =>
  `SELECT mod_release FROM modules_informations WHERE name = '${moduleName}'`;

const buildWidgetCountQuery = (directory: string): string =>
  `SELECT COUNT(*) AS count FROM widget_models WHERE directory = '${directory}'`;

const duplicatedModulesQuery =
  'SELECT name FROM modules_informations GROUP BY name HAVING COUNT(*) > 1';

const duplicatedWidgetsQuery =
  'SELECT directory FROM widget_models GROUP BY directory HAVING COUNT(*) > 1';

const duplicatedMenuEntriesQuery =
  'SELECT topology_page FROM topology WHERE topology_page IS NOT NULL GROUP BY topology_page HAVING COUNT(*) > 1 ORDER BY topology_page';

// Database partitioning and purge

const partitionedTables = [
  'data_bin',
  'logs',
  'log_archive_host',
  'log_archive_service'
];

const partitioningScript = 'centreon-partitioning.php';
const purgeScript = 'centstorage_purge.php';
const partitioningLog = '/var/log/centreon/centreon-partitioning.log';
const purgeLog = '/var/log/centreon/centreon-purge.log';

const storageDatabase = 'centreon_storage';

// Runs the line of a cron.d file calling the script, as the user of that line
const buildCronLineRunCommand = (cronFile: string, script: string): string =>
  [
    `line=$(grep -v '^#' ${cronFile} | grep -F '${script}' | head -n 1)`,
    `[ -n "$line" ] || { echo "no ${script} line in ${cronFile}"; exit 1; }`,
    `user=$(echo "$line" | awk '{print $6}')`,
    `command=$(echo "$line" | awk '{$1=$2=$3=$4=$5=$6=""; sub(/^ +/, ""); print}')`,
    'echo "running as $user: $command"',
    'su -s /bin/bash "$user" -c "$command"'
  ].join('\n');

const buildLineCountCommand = (file: string): string =>
  `if [ -f ${file} ]; then wc -l < ${file}; else echo 0; fi`;

const buildLinesAfterCommand = (file: string, lineCount: number): string =>
  `tail -n +${lineCount + 1} ${file}`;

// Same computations as the product (local time of the PHP CLI)
const forwardPartitionNameCommand = (days: number): string =>
  `php -r 'echo "p" . date("Ymd", mktime(0, 0, 0, (int) date("n"), (int) date("j") + ${days}, (int) date("Y")));'`;

const retentionLimitCommand = (days: number): string =>
  `php -r 'echo mktime(0, 0, 0, (int) date("n"), (int) date("j") - ${days}, (int) date("Y"));'`;

const buildPartitionsQuery = (table: string): string =>
  `SELECT PARTITION_NAME AS name, PARTITION_DESCRIPTION AS description FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = '${storageDatabase}' AND TABLE_NAME = '${table}' AND PARTITION_NAME IS NOT NULL ORDER BY PARTITION_ORDINAL_POSITION`;

const buildPartitionRowCountQuery = (
  table: string,
  partition: string
): string =>
  `SELECT COUNT(*) AS count FROM ${table} PARTITION (\`${partition}\`)`;

// Splits the first partition in two so that a new, older one comes first
const buildPrependPartitionQuery = ({
  firstPartition,
  firstPartitionBound,
  newPartition,
  newPartitionBound,
  table
}: {
  firstPartition: string;
  firstPartitionBound: string;
  newPartition: string;
  newPartitionBound: number;
  table: string;
}): string =>
  `ALTER TABLE ${table} REORGANIZE PARTITION \`${firstPartition}\` INTO (PARTITION \`${newPartition}\` VALUES LESS THAN (${newPartitionBound}), PARTITION \`${firstPartition}\` VALUES LESS THAN (${firstPartitionBound}))`;

const buildInsertLogLineQuery = (output: string, ctime: number): string =>
  `INSERT INTO logs (ctime, host_name, instance_name, msg_type, output, status, type) VALUES (${ctime}, 'rbt-purge-host', 'Central', 0, '${output}', 0, 0)`;

const buildLogLineCountQuery = (output: string): string =>
  `SELECT COUNT(*) AS count FROM logs WHERE output = '${output}'`;

const buildPartitioningOptionsQueries = ({
  forwardDays,
  logsRetentionDays
}: {
  forwardDays: number;
  logsRetentionDays: number;
}): Array<{ database: string; query: string }> => [
  {
    database: 'centreon',
    query: `UPDATE options SET \`value\` = '${forwardDays}' WHERE \`key\` = 'partitioning_retention_forward'`
  },
  {
    database: storageDatabase,
    query: `UPDATE config SET archive_retention = ${logsRetentionDays} WHERE id = 1`
  }
];

const partitioningOptionsCheckQuery =
  "SELECT `value` FROM options WHERE `key` = 'partitioning_retention_forward'";

const logsRetentionCheckQuery =
  'SELECT archive_retention FROM config WHERE id = 1';

export {
  buildCronLineRunCommand,
  buildInsertLogLineQuery,
  buildInstalledModuleQuery,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildLogLineCountQuery,
  buildPartitioningOptionsQueries,
  buildPartitionRowCountQuery,
  buildPartitionsQuery,
  buildPrependPartitionQuery,
  buildWidgetCountQuery,
  buildWriteModuleCommand,
  duplicatedMenuEntriesQuery,
  duplicatedModulesQuery,
  duplicatedWidgetsQuery,
  forwardPartitionNameCommand,
  logsRetentionCheckQuery,
  partitionedTables,
  partitioningLog,
  partitioningOptionsCheckQuery,
  partitioningScript,
  purgeLog,
  purgeScript,
  retentionLimitCommand,
  storageDatabase
};
