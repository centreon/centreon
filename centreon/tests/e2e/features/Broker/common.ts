// Passive service shared by the Broker scenarios, named as in their Xray steps
const passiveHost = 'rbt-bbdo-host';
const passiveService = 'rbt-bbdo-svc';
const metricName = 'rbt_metric';
// warning;critical;min;max appended to every value of the metric
const metricThresholds = { critical: 90, max: 100, min: 0, warning: 80 };

const apiLatest = '/centreon/api/latest';

// Broker configurations of the Central, as installed
const brokerConfigurationNames = {
  broker: 'central-broker-master',
  module: 'central-module-master',
  rrd: 'central-rrd-master'
};

const brokerConfigurationFiles = {
  broker: '/etc/centreon-broker/central-broker.json',
  module: '/etc/centreon-broker/central-module.json',
  rrd: '/etc/centreon-broker/central-rrd.json'
};

// Statistics written by each Broker instance (named pipes, read with cat)
const brokerStatsFiles = {
  broker: '/var/lib/centreon-broker/central-broker-master-stats.json',
  module: '/var/lib/centreon-engine/central-module-master-stats.json',
  rrd: '/var/lib/centreon-broker/central-rrd-master-stats.json'
};

const brokerEndpoints = {
  brokerInput: 'central-broker-master-input',
  moduleOutput: 'central-module-master-output',
  rrdForwardOutput: 'centreon-broker-master-rrd',
  rrdInput: 'central-rrd-master-input',
  rrdOutput: 'central-rrd-master-output',
  unifiedSqlOutput: 'central-broker-master-unified-sql'
};

const brokerLogFiles = {
  broker: '/var/log/centreon-broker/central-broker-master.log',
  engine: '/var/log/centreon-engine/centengine.log',
  module: '/var/log/centreon-broker/central-module-master.log',
  rrd: '/var/log/centreon-broker/central-rrd-master.log'
};

const brokerVarLibDirectory = '/var/lib/centreon-broker';
const engineCommandFile = '/var/lib/centreon-engine/rw/centengine.cmd';
const metricsRrdDirectory = '/var/lib/centreon/metrics';

// Queued events below this count: no backlog (Xray expected results)
const maxQueuedEvents = 100;

// The Broker statistics page colours these states green
const connectedState = 'connected';
const listeningState = 'listening';

interface EndpointStats {
  queued_events?: number;
  state?: string;
  [key: string]: unknown;
}

type BrokerStats = Record<string, unknown>;

interface CheckResult {
  output: string;
  status: number;
}

interface Handshake {
  ours: string;
  peer: string;
}

const endpointStats = (
  stats: BrokerStats,
  endpoint: string
): EndpointStats | undefined =>
  stats[`endpoint ${endpoint}`] as EndpointStats | undefined;

// Peers accepted by an input endpoint: one sub-object per connection,
// named after the endpoint with a connection number (<endpoint>-<n>)
const connectedPeers = (
  stats: BrokerStats,
  endpoint: string
): Array<string> => {
  const input = endpointStats(stats, endpoint) ?? {};

  return Object.entries(input)
    .filter(
      ([key, value]) =>
        new RegExp(`^${endpoint}-\\d+$`).test(key) &&
        (value as EndpointStats | null)?.state === connectedState
    )
    .map(([key]) => key);
};

const isOutputConnectedWithoutBacklog = (
  stats: BrokerStats,
  endpoint: string
): boolean => {
  const output = endpointStats(stats, endpoint);

  return (
    output?.state === connectedState &&
    Number(output.queued_events ?? 0) < maxQueuedEvents
  );
};

// "BBDO: peer is using protocol version 3.1.0, we're using version 3.1.0"
// (a variant of the message has a space before the comma)
const parseHandshakes = (log: string): Array<Handshake> =>
  [
    ...log.matchAll(
      /BBDO: peer is using protocol version (\d+\.\d+\.\d+) ?, we're using version (\d+\.\d+\.\d+)/g
    )
  ].map(([, peer, ours]) => ({ ours, peer }));

const perfdataOf = (value: number): string =>
  `${metricName}=${value};${metricThresholds.warning};${metricThresholds.critical};${metricThresholds.min};${metricThresholds.max}`;

// Writes passive results in the Engine command file, one per second as in
// the Xray steps, and prints the check time of each: "submitted <time>"
const buildSubmitResultsCommand = (
  host: string,
  service: string,
  results: Array<CheckResult>
): string =>
  results
    .map(
      ({ output, status }) =>
        `t=$(date +%s); printf '[%s] PROCESS_SERVICE_CHECK_RESULT;${host};${service};${status};%s\\n' "$t" '${output}' > ${engineCommandFile}; echo "submitted $t"; sleep 1`
    )
    .join('; ');

// Same with explicit check times (epoch seconds), written at once
const buildSubmitTimedResultsCommand = (
  host: string,
  service: string,
  results: Array<CheckResult & { time: number }>
): string =>
  results
    .map(
      ({ output, status, time }) =>
        `printf '[%s] PROCESS_SERVICE_CHECK_RESULT;${host};${service};${status};%s\\n' '${time}' '${output}' > ${engineCommandFile}; echo "submitted ${time}"`
    )
    .join('; ');

const parseSubmittedTimes = (output: string): Array<number> =>
  [...output.matchAll(/submitted (\d+)/g)].map(([, time]) => Number(time));

// Alternating Critical / Ok results "<prefix>-01".."<prefix>-<n>": with one
// check attempt every result is a hard state change of an Ok service
const buildStateChanges = (
  prefix: string,
  count: number,
  first = 1,
  withPerfdata = false
): Array<CheckResult> =>
  Array.from({ length: count }, (_, index) => {
    const rank = first + index;
    const label = `${prefix}-${String(rank).padStart(2, '0')}`;

    return {
      output: withPerfdata ? `${label}|${perfdataOf(rank)}` : label,
      status: rank % 2 === 1 ? 2 : 0
    };
  });

const buildCountProcessCommand = (pattern: string): string =>
  `ps -eo args= | grep -cE '${pattern}' || true`;

const buildEngineAlertCountCommand = (
  host: string,
  service: string,
  outputPrefix: string
): string =>
  `grep -c 'SERVICE ALERT: ${host};${service};.*${outputPrefix}-[0-9][0-9]' ${brokerLogFiles.engine} || true`;

const buildQueueFileCountCommand = (): string =>
  `ls ${brokerVarLibDirectory} | grep -c '\\.queue\\.' || true`;

const buildReadStatsCommand = (statsFile: string): string =>
  `timeout 30 cat ${statsFile}`;

// Number of lines of a log file, to read only what is written afterwards
const buildLineCountCommand = (file: string): string =>
  `[ -f ${file} ] && wc -l < ${file} || echo 0`;

const buildLinesAfterCommand = (file: string, lineCount: number): string =>
  `[ -f ${file} ] && tail -n +${lineCount + 1} ${file} || true`;

// Start, stop or restart a service of the web container
const buildServiceCommand = (action: string, service: string): string =>
  `systemctl ${action} ${service} > /dev/null`;

// SQL through the mysql client of the web container, for a database whose
// host port may have changed (stopped and started again during the test)
const buildMysqlCommand = (database: string, query: string): string =>
  `mysql -h "\${MYSQL_HOST:-db}" -u centreon -pcentreon -N -B ${database} -e "${query
    .replace(/\\/g, '\\\\')
    .replace(/"/g, '\\"')
    .replace(/\$/g, '\\$')
    .replace(/`/g, '\\`')}"`;

const parseMysqlRows = (output: string): Array<Array<string>> =>
  output
    .split('\n')
    .filter((line) => line.trim() !== '' && !/^mysql: /.test(line))
    .map((line) => line.split('\t'));

const buildBrokerBbdoVersionsQuery = (): string =>
  `SELECT config_name, bbdo_version FROM cfg_centreonbroker
    WHERE config_name IN ('${Object.values(brokerConfigurationNames).join("', '")}')`;

// Log level of the bbdo logger of Broker configurations (the default level,
// error, does not log the BBDO handshake)
const buildSetBbdoLogLevelQuery = (
  configNames: Array<string>,
  level: string
): string =>
  `UPDATE cfg_centreonbroker_log l
    INNER JOIN cfg_centreonbroker c ON c.config_id = l.id_centreonbroker
    INNER JOIN cb_log g ON g.id = l.id_log
    INNER JOIN cb_log_level v ON v.name = '${level}'
    SET l.id_level = v.id
    WHERE g.name = 'bbdo' AND c.config_name IN ('${configNames.join("', '")}')`;

const buildHostAndServiceResourcesQuery = (host: string): string =>
  `SELECT type, name, parent_name, enabled FROM resources
    WHERE (type = 1 AND name = '${host}') OR (type = 0 AND parent_name = '${host}')`;

const buildHostGroupTagsQuery = (host: string): string =>
  `SELECT t.name FROM resources r
    INNER JOIN resources_tags rt ON rt.resource_id = r.resource_id
    INNER JOIN tags t ON t.tag_id = rt.tag_id
    WHERE r.type = 1 AND r.name = '${host}' AND r.enabled = 1 AND t.type = 1`;

const buildServiceStateQuery = (host: string, service: string): string =>
  `SELECT s.state, s.output, s.perfdata FROM services s
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${host}' AND s.description = '${service}' AND s.enabled = 1`;

const buildMetricQuery = (
  host: string,
  service: string,
  metric: string
): string =>
  `SELECT i.id AS index_id, m.metric_id, m.warn, m.crit, m.min, m.max
    FROM index_data i
    INNER JOIN metrics m ON m.index_id = i.id
    WHERE i.host_name = '${host}' AND i.service_description = '${service}'
      AND m.metric_name = '${metric}'`;

const buildMetricValuesQuery = (metricId: number, since: number): string =>
  `SELECT ctime, value FROM data_bin
    WHERE id_metric = ${metricId} AND ctime >= ${since} ORDER BY ctime`;

// Service alerts (msg_type 0) of the event log whose output is <prefix>-NN
const buildServiceAlertsQuery = (
  host: string,
  service: string,
  outputPrefix: string
): string =>
  `SELECT COUNT(*) AS count, COUNT(DISTINCT output) AS distinct_count
    FROM logs
    WHERE host_name = '${host}' AND service_description = '${service}'
      AND msg_type = 0 AND output LIKE '${outputPrefix}-__'`;

const buildServiceResourceQuery = (host: string, service: string): string =>
  `SELECT status, output FROM resources
    WHERE type = 0 AND name = '${service}' AND parent_name = '${host}' AND enabled = 1`;

const buildServiceIdsQuery = (host: string, service: string): string =>
  `SELECT s.host_id, s.service_id FROM services s
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${host}' AND s.description = '${service}' AND s.enabled = 1`;

const metricRrdFile = (metricId: number): string =>
  `${metricsRrdDirectory}/${metricId}.rrd`;

const buildRrdLastUpdateCommand = (metricId: number): string =>
  `stat -c '%U' ${metricRrdFile(metricId)} && rrdtool lastupdate ${metricRrdFile(metricId)}`;

const buildRrdErrorCountCommand = (metricId: number): string =>
  `grep -F '/${metricId}.rrd' ${brokerLogFiles.rrd} | grep -c '\\[error\\]' || true`;

// rrdtool lastupdate prints the data source names, then "<time>: <value>"
const parseRrdLastUpdate = (
  output: string
): { owner: string; time: number; value: number } | null => {
  const match = output.match(/^(\d+):\s+(\S+)\s*$/m);

  return match
    ? {
        owner: output.trim().split('\n')[0].trim(),
        time: Number(match[1]),
        value: Number(match[2])
      }
    : null;
};

// Root CA of the TLS overlay (.github/docker/docker-compose.tls.yml)
const databaseTlsCaPath = '/etc/pki/centreon-tls/rootCA.pem';
const webEnvFile = '/usr/share/centreon/.env.local.php';

// Database TLS keys read by the PHP side (configuration generation included),
// staged in .env.local.php as unattended.sh does (configure_db_tls)
const buildStageDatabaseTlsCommand = (caPath: string): string =>
  [
    `sed -i -E "/^[[:space:]]*'DATABASE_(SSL_ENABLED|VERIFY_SERVER_CERT|CA_PATH)'[[:space:]]*=>/d" ${webEnvFile}`,
    String.raw`sed -i -E "s#^([[:space:]]*[])][[:space:]]*;[[:space:]]*)\$#    'DATABASE_SSL_ENABLED' => '1',\n    'DATABASE_VERIFY_SERVER_CERT' => '1',\n    'DATABASE_CA_PATH' => '${caPath}',\n\1#" ${webEnvFile}`,
    `grep -c "'DATABASE_SSL_ENABLED' => '1'" ${webEnvFile}`
  ].join(' && ');

// Generate and move the configuration of the Central with the CLI, as the
// web server user
const buildExportCentralCommand = (): string =>
  `u=$(id -u apache > /dev/null 2>&1 && echo apache || echo www-data); su "$u" -s /bin/bash -c "centreon -u admin -p 'Centreon!2021' -a POLLERGENERATE -v 1 && centreon -u admin -p 'Centreon!2021' -a CFGMOVE -v 1"`;

const buildBrokerPidsCommand = (): string =>
  String.raw`ps -eo pid=,args= | grep -E '[ /]cbd .*central-(broker|rrd)\.json' | awk '{print $1}' | paste -sd, -`;

const buildRootMysqlCommand = (query: string): string =>
  `mysql -u root -pcentreon -N -B -e "${query}"`;

// Connection type of the database sessions opened by the given processes
// (needs performance_schema), run as root in the database container
const buildSessionsConnectionTypeCommand = (pids: string): string =>
  buildRootMysqlCommand(
    `SELECT t.PROCESSLIST_ID, IFNULL(t.CONNECTION_TYPE, 'NONE') FROM performance_schema.threads t INNER JOIN performance_schema.session_connect_attrs a ON a.PROCESSLIST_ID = t.PROCESSLIST_ID WHERE a.ATTR_NAME = '_pid' AND FIND_IN_SET(a.ATTR_VALUE, '${pids}') > 0`
  );

export {
  apiLatest,
  type BrokerStats,
  brokerConfigurationFiles,
  brokerConfigurationNames,
  brokerEndpoints,
  brokerLogFiles,
  brokerStatsFiles,
  buildBrokerBbdoVersionsQuery,
  buildBrokerPidsCommand,
  buildCountProcessCommand,
  buildEngineAlertCountCommand,
  buildExportCentralCommand,
  buildHostAndServiceResourcesQuery,
  buildHostGroupTagsQuery,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildMetricQuery,
  buildMetricValuesQuery,
  buildMysqlCommand,
  buildQueueFileCountCommand,
  buildReadStatsCommand,
  buildRrdErrorCountCommand,
  buildRrdLastUpdateCommand,
  buildServiceAlertsQuery,
  buildServiceCommand,
  buildServiceIdsQuery,
  buildServiceResourceQuery,
  buildServiceStateQuery,
  buildSessionsConnectionTypeCommand,
  buildSetBbdoLogLevelQuery,
  buildStageDatabaseTlsCommand,
  buildStateChanges,
  buildSubmitResultsCommand,
  buildSubmitTimedResultsCommand,
  type CheckResult,
  connectedPeers,
  connectedState,
  databaseTlsCaPath,
  endpointStats,
  isOutputConnectedWithoutBacklog,
  listeningState,
  maxQueuedEvents,
  metricName,
  metricsRrdDirectory,
  metricThresholds,
  parseHandshakes,
  parseMysqlRows,
  parseRrdLastUpdate,
  parseSubmittedTimes,
  passiveHost,
  passiveService,
  perfdataOf
};
