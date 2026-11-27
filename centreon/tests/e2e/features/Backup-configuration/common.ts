const centreonCronFile = '/etc/cron.d/centreon';
const backupScript = 'centreon-backup.pl';
const restoreDatabase = 'rbt_backup_restore';

// Options of Administration > Parameters > Backup ("Dump" is the value 0)
const buildBackupOptions = (fullBackupDay: string): Record<string, string> => ({
  backup_configuration_files: '1',
  backup_database_centreon: '1',
  backup_database_centreon_storage: '1',
  backup_database_full: fullBackupDay,
  backup_database_partial: '',
  backup_database_type: '0',
  backup_enabled: '1'
});

const buildSetOptionQuery = (key: string, value: string): string =>
  `UPDATE options SET \`value\` = '${value}' WHERE \`key\` = '${key}'`;

const backupOptionsQuery =
  "SELECT `key`, `value` FROM options WHERE `key` LIKE 'backup\\_%'";

const configuredHostsQuery =
  "SELECT host_name FROM host WHERE host_register = '1' ORDER BY host_name";

const buildCreateDatabaseQuery = (database: string): string =>
  `CREATE DATABASE \`${database}\``;

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

// Backup files are named after the local date of the central (localtime in Perl)
const todayCommand = 'date +%F';
const dayOfWeekCommand = 'date +%w';

const buildBackupFiles = (
  directory: string,
  today: string
): Record<string, string> => ({
  centralArchive: `${directory}/${today}-central.tar.gz`,
  centreonDump: `${directory}/${today}-centreon.sql.gz`,
  centreonEngineArchive: `${directory}/${today}-centreon-engine.tar.gz`,
  centreonStorageDump: `${directory}/${today}-centreon_storage.sql.gz`
});

const buildDumpLastLineCommand = (file: string): string =>
  `gzip -t ${file} && gunzip -c ${file} | tail -n 1`;

const buildRestoreDumpCommand = (file: string, database: string): string =>
  `set -o pipefail; gunzip -c ${file} | mysql -h"$MYSQL_HOST" -uroot -p"$MYSQL_ROOT_PASSWORD" ${database}`;

const buildArchiveListCommand = (file: string): string => `tar -tzf ${file}`;

// mysqldump ends a complete dump with this comment
const dumpCompletedPattern = /^-- Dump completed on \d{4}-\d{2}-\d{2}/;

const centreonConfigurationFiles = [
  'central/etc/centreon/centreon.conf.php',
  'central/etc/centreon/conf.pm'
];

export {
  backupOptionsQuery,
  backupScript,
  buildArchiveListCommand,
  buildBackupFiles,
  buildBackupOptions,
  buildCreateDatabaseQuery,
  buildCronLineRunCommand,
  buildDumpLastLineCommand,
  buildRestoreDumpCommand,
  buildSetOptionQuery,
  centreonConfigurationFiles,
  centreonCronFile,
  configuredHostsQuery,
  dayOfWeekCommand,
  dumpCompletedPattern,
  restoreDatabase,
  todayCommand
};
