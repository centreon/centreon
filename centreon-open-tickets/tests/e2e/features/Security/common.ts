interface SelectedService {
  host: string;
  hostId: number;
  service: string;
  serviceId: number;
}

interface ExternalCommand {
  host: string;
  name: string;
  raw: string;
  service: string | null;
}

interface WidgetActionResult {
  code: number;
  msg: string;
}

// Gorgone logs every external command it forwards to Engine
const gorgoneLogFile = '/var/log/centreon-gorgone/gorgoned.log';

// SQL errors of the web go to prod.web.log, PHP warnings to the php-fpm logs
const watchedLogGlobs = ['/var/log/centreon/*.log', '/var/log/php-fpm/*.log'];

const sqlErrorPattern =
  /SQLSTATE|PDOException|SQL syntax|Error while fetching tickets/i;

const phpWarningPattern =
  /PHP (Warning|Fatal error)|Undefined array key|Undefined offset/i;

// External commands whose second argument is a service description
const serviceCommandNames = [
  'ACKNOWLEDGE_SVC_PROBLEM',
  'CHANGE_CUSTOM_SVC_VAR',
  'REMOVE_SVC_ACKNOWLEDGEMENT',
  'SCHEDULE_FORCED_SVC_CHECK',
  'SCHEDULE_SVC_CHECK'
];

// Selection as the widget posts it: "hostId;serviceId" entries joined by commas
const buildPlainSelection = (services: Array<SelectedService>): string =>
  services.map(({ hostId, serviceId }) => `${hostId};${serviceId}`).join(',');

// Same resources, each one written with SQL injection attempts. No comma nor
// semicolon inside a payload: both are the separators of the selection.
const buildInjectedSelection = (services: Array<SelectedService>): string =>
  services
    .flatMap(({ hostId, serviceId }) => [
      `${hostId};${serviceId} OR 1=1`,
      `${hostId}) UNION SELECT NULL-- -;${serviceId}`,
      `${hostId}' OR '1'='1;${serviceId}`,
      `${hostId};${serviceId} AND SLEEP(5)`
    ])
    .join(',');

const parseWidgetActionResult = (body: unknown): WidgetActionResult =>
  (typeof body === 'string' ? JSON.parse(body) : body) as WidgetActionResult;

// "... Parameters: '[1795792577] ACKNOWLEDGE_SVC_PROBLEM;host;service;...'"
const parseGorgoneCommands = (output: string): Array<ExternalCommand> =>
  output
    .split('\n')
    .map((line) => line.match(/Parameters: '\[\d+\] (.*)'\s*$/)?.[1])
    .filter((raw): raw is string => raw !== undefined)
    .map((raw) => {
      const [name, host, service] = raw.split(';');

      return {
        host,
        name,
        raw,
        service: serviceCommandNames.includes(name) ? service : null
      };
    });

// Sorted "host;service" targets, a host command being "host;"
const commandTargets = (commands: Array<ExternalCommand>): Array<string> =>
  [
    ...new Set(commands.map(({ host, service }) => `${host};${service ?? ''}`))
  ].sort();

const buildFileSizeCommand = (file: string): string =>
  `stat -c %s ${file} 2>/dev/null || echo 0`;

const buildGorgoneCommandsCommand = (offset: number): string =>
  `tail -c +${offset + 1} ${gorgoneLogFile} | grep "Handling command 'EXTERNALCMD'" || true`;

const buildLogSizesCommand = (): string =>
  `for f in ${watchedLogGlobs.join(' ')}; do [ -f "$f" ] && echo "$f $(stat -c %s "$f")"; done; true`;

const parseLogSizes = (output: string): Record<string, number> =>
  Object.fromEntries(
    output
      .split('\n')
      .map((line) => line.trim().split(' '))
      .filter((parts) => parts.length === 2)
      .map(([file, size]) => [file, Number(size)])
  );

// Content written to the watched logs since the sizes were taken; a log
// created afterwards is read from its start
const buildNewLogContentCommand = (sizes: Record<string, number>): string => {
  const offsets = Object.entries(sizes)
    .map(([file, size]) => `${file}) s=${size};;`)
    .join(' ');

  return `for f in ${watchedLogGlobs.join(' ')}; do [ -f "$f" ] || continue; s=0; case "$f" in ${offsets} esac; tail -c +$((s+1)) "$f"; done; true`;
};

const buildServiceIdsQuery = (host: string, service: string): string =>
  `SELECT h.host_id, s.service_id FROM hosts h INNER JOIN services s ON s.host_id = h.host_id WHERE h.name = '${host}' AND s.description = '${service}' AND s.enabled = 1`;

const buildAcknowledgedCountQuery = (
  services: Array<SelectedService>
): string => {
  const hostIds = services.map(({ hostId }) => hostId).join(', ');
  const serviceConditions = services
    .map(
      ({ hostId, serviceId }) =>
        `(host_id = ${hostId} AND service_id = ${serviceId})`
    )
    .join(' OR ');

  return `SELECT (SELECT COUNT(*) FROM hosts WHERE acknowledged = 1 AND host_id IN (${hostIds})) AS hosts, (SELECT COUNT(*) FROM services WHERE acknowledged = 1 AND (${serviceConditions})) AS services`;
};

const buildRuleQuery = (alias: string): string =>
  `SELECT rule_id, provider_id FROM mod_open_tickets_rule WHERE alias = '${alias}'`;

const buildInsertTicketQuery = (ticketValue: string): string =>
  `INSERT INTO mod_open_tickets (\`timestamp\`, \`user\`, ticket_value) VALUES (UNIX_TIMESTAMP(), 'admin', '${ticketValue}')`;

const buildInsertTicketLinkQuery = (
  ticketValue: string,
  { host, hostId, service, serviceId }: SelectedService
): string =>
  `INSERT INTO mod_open_tickets_link (ticket_id, host_id, service_id, host_state, service_state, hostname, service_description) SELECT ticket_id, ${hostId}, ${serviceId}, 1, 2, '${host}', '${service}' FROM mod_open_tickets WHERE ticket_value = '${ticketValue}'`;

export {
  buildAcknowledgedCountQuery,
  buildFileSizeCommand,
  buildGorgoneCommandsCommand,
  buildInjectedSelection,
  buildInsertTicketLinkQuery,
  buildInsertTicketQuery,
  buildLogSizesCommand,
  buildNewLogContentCommand,
  buildPlainSelection,
  buildRuleQuery,
  buildServiceIdsQuery,
  commandTargets,
  gorgoneLogFile,
  parseGorgoneCommands,
  parseLogSizes,
  parseWidgetActionResult,
  phpWarningPattern,
  sqlErrorPattern
};
export type { ExternalCommand, SelectedService, WidgetActionResult };
