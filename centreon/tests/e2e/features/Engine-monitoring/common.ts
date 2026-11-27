interface CfgObject {
  [key: string]: string;
}

interface RetentionBlock {
  fields: Record<string, string>;
  type: string;
}

const pollerName = 'Central';

// Directory of the test check script, readable by the centreon-engine user
const testCheckDirectory = '/var/lib/centreon-engine/rbt-e2e';
const testCheckScript = `${testCheckDirectory}/rbt-check.sh`;

// Host check command shared by the hosts of the domain: always Up
const hostCheckCommandName = 'rbt-host-check';

// Directories Engine reads its command whitelist from
const engineWhitelistDirectories = [
  '/etc/centreon-engine-whitelist',
  '/usr/share/centreon-engine/whitelist.conf.d'
];

// Generated configuration deployed to Engine by the export
const engineConfigurationDirectory = '/etc/centreon-engine';

const weekDays = [
  'sunday',
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday'
];

const exitCodes = {
  critical: 2,
  ok: 0,
  unknown: 3,
  warning: 1
};

// Shell single-quoted literal
const shellQuote = (value: string): string =>
  `'${value.replace(/'/g, `'\\''`)}'`;

// The check script prints and returns the exit code written in <name>.code
// (0 when missing) after sleeping the seconds written in <name>.sleep.
// Called with the service description, or without argument for a host.
const testCheckScriptLines = [
  '#!/bin/sh',
  'name="$1"',
  '[ -n "$name" ] || name=host',
  `dir=${testCheckDirectory}`,
  'code=$(cat "$dir/$name.code" 2>/dev/null || echo 0)',
  'pause=$(cat "$dir/$name.sleep" 2>/dev/null || echo 0)',
  'if [ "$pause" -gt 0 ]; then sleep "$pause"; fi',
  'case "$code" in 0) label=OK ;; 1) label=WARNING ;; 2) label=CRITICAL ;; *) label=UNKNOWN ;; esac',
  'echo "$label - rbt check of $name returned $code"',
  'exit "$code"'
];

const whitelistContent = [
  'whitelist:',
  '  wildcard:',
  `    - "${testCheckScript}"`,
  `    - "${testCheckScript} *"`
];

// Installs the check script. When a command whitelist is already active on
// the poller, the script is added to it, otherwise Engine would refuse it.
const buildInstallTestCheckCommand = (): string =>
  [
    `mkdir -p ${testCheckDirectory}`,
    `chmod 755 ${testCheckDirectory}`,
    `cat > ${testCheckScript} <<'RBT_EOF'`,
    ...testCheckScriptLines,
    'RBT_EOF',
    `chmod 755 ${testCheckScript}`,
    `if find ${engineWhitelistDirectories.join(' ')} -maxdepth 1 -type f 2>/dev/null | grep -q .; then`,
    `  mkdir -p ${engineWhitelistDirectories[0]}`,
    `  cat > ${engineWhitelistDirectories[0]}/rbt-e2e.yaml <<'RBT_EOF'`,
    ...whitelistContent,
    'RBT_EOF',
    `  chown root:centreon-engine ${engineWhitelistDirectories[0]}/rbt-e2e.yaml`,
    `  chmod 640 ${engineWhitelistDirectories[0]}/rbt-e2e.yaml`,
    'fi'
  ].join('\n');

const buildSetTestCheckBehaviourCommand = (
  name: string,
  exitCode: number,
  sleepSeconds: number
): string =>
  `printf '%s\\n' ${exitCode} > ${testCheckDirectory}/${name}.code && printf '%s\\n' ${sleepSeconds} > ${testCheckDirectory}/${name}.sleep && chmod 644 ${testCheckDirectory}/${name}.code ${testCheckDirectory}/${name}.sleep`;

// Writes one external command to the Engine command file (a FIFO): the write
// is bounded so that a missing reader fails the step instead of hanging
const buildWriteExternalCommand = (
  commandFile: string,
  command: string,
  timestamp: number
): string =>
  `printf '%s\\n' ${shellQuote(`[${timestamp}] ${command}`)} | timeout 15 tee -a ${commandFile} > /dev/null`;

const buildLogLineCountCommand = (logFile: string): string =>
  `wc -l < ${logFile}`;

const buildLogLinesAfterCommand = (
  logFile: string,
  fromLine: number,
  text: string
): string =>
  `tail -n +${fromLine + 1} ${logFile} | grep -F -- ${shellQuote(text)} || true`;

// Prints "<processes seen> <oldest age in seconds>" for the check processes
// of a service, sampled every <interval> seconds <samples> times. The
// pattern is split so that this shell never matches itself.
const buildCheckProcessAgeCommand = (
  service: string,
  samples: number,
  interval: number
): string =>
  [
    `script=${testCheckScript}`,
    `svc=${service}`,
    'hz=$(getconf CLK_TCK)',
    'seen=0',
    'max=0',
    `for i in $(seq 1 ${samples}); do`,
    '  up=$(cut -d. -f1 /proc/uptime)',
    '  for d in /proc/[0-9]*; do',
    '    args=$(tr "\\0" " " < "$d/cmdline" 2>/dev/null) || continue',
    '    case "$args" in *"$script $svc"*) ;; *) continue ;; esac',
    '    start=$(cut -d" " -f22 "$d/stat" 2>/dev/null) || continue',
    '    [ -n "$start" ] || continue',
    '    age=$((up - start / hz))',
    '    seen=$((seen + 1))',
    '    [ "$age" -gt "$max" ] && max=$age',
    '  done',
    `  sleep ${interval}`,
    'done',
    'echo "$seen $max"'
  ].join('\n');

const processServiceCheckResult = (
  host: string,
  service: string,
  exitCode: number,
  output: string
): string =>
  `PROCESS_SERVICE_CHECK_RESULT;${host};${service};${exitCode};${output}`;

const acknowledgeServiceProblem = (
  host: string,
  service: string,
  comment: string
): string =>
  // sticky=2, notify=0, persistent=1
  `ACKNOWLEDGE_SVC_PROBLEM;${host};${service};2;0;1;admin;${comment}`;

const scheduleFixedServiceDowntime = (
  host: string,
  service: string,
  start: number,
  duration: number,
  comment: string
): string =>
  `SCHEDULE_SVC_DOWNTIME;${host};${service};${start};${start + duration};1;0;${duration};admin;${comment}`;

const addPersistentServiceComment = (
  host: string,
  service: string,
  comment: string
): string => `ADD_SVC_COMMENT;${host};${service};1;admin;${comment}`;

// Engine settings of a poller, from the configuration database
const buildEngineSettingsQuery = (): string =>
  `SELECT c.nagios_name, c.command_file, c.log_file, c.state_retention_file
    FROM cfg_nagios c
    INNER JOIN nagios_server ns ON ns.id = c.nagios_server_id
    WHERE ns.name = ? AND c.nagios_activate = '1'`;

const buildEngineInstanceQuery = (): string =>
  'SELECT pid, running FROM instances WHERE name = ? AND deleted = 0';

const buildServiceStatusQuery = (): string =>
  `SELECT s.state, s.state_type, s.check_attempt, s.checked, s.check_type,
      s.last_check, s.next_check, s.output, s.perfdata, s.execution_time,
      s.acknowledged, s.scheduled_downtime_depth, s.enabled,
      r.status AS resource_status, r.acknowledged AS resource_acknowledged,
      r.in_downtime AS resource_in_downtime, r.enabled AS resource_enabled
    FROM services s
    INNER JOIN hosts h ON h.host_id = s.host_id
    LEFT JOIN resources r ON r.id = s.service_id AND r.parent_id = s.host_id AND r.type = 0
    WHERE h.name = ? AND s.description = ?
    ORDER BY s.enabled DESC
    LIMIT 1`;

const buildHostStatusQuery = (): string =>
  `SELECT h.state, h.checked, h.check_type, h.last_check, h.output, h.enabled,
      r.status AS resource_status, r.last_check AS resource_last_check,
      r.output AS resource_output, r.enabled AS resource_enabled
    FROM hosts h
    LEFT JOIN resources r ON r.id = h.host_id AND r.parent_id = 0 AND r.type = 1
    WHERE h.name = ?
    ORDER BY h.enabled DESC
    LIMIT 1`;

const buildServiceCommentQuery = (): string =>
  `SELECT c.comment_id, c.persistent, c.entry_time
    FROM comments c
    INNER JOIN hosts h ON h.host_id = c.host_id
    INNER JOIN services s ON s.host_id = c.host_id AND s.service_id = c.service_id
    WHERE h.name = ? AND s.description = ? AND c.data = ? AND c.deletion_time IS NULL`;

// Objects of a generated Engine configuration file (define <type> { ... })
const parseCfgObjects = (content: string, type: string): Array<CfgObject> =>
  content
    .split(new RegExp(`define\\s+${type}\\s*\\{`))
    .slice(1)
    .map((block) =>
      block
        .split('}')[0]
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '' && !line.startsWith('#'))
        .reduce<CfgObject>((object, line) => {
          const [key, ...value] = line.split(/\s+/);

          return { ...object, [key]: value.join(' ') };
        }, {})
    );

// Blocks of an Engine retention file (<type> {\nkey=value\n...}\n)
const parseRetentionBlocks = (content: string): Array<RetentionBlock> =>
  content
    .split(/\n}\s*\n?/)
    .map((block) => block.trim())
    .filter((block) => /^\w+\s*\{/.test(block))
    .map((block) => {
      const [header, ...lines] = block.split('\n');

      return {
        fields: lines.reduce<Record<string, string>>((fields, line) => {
          const separator = line.indexOf('=');

          if (separator < 0) {
            return fields;
          }

          return {
            ...fields,
            [line.slice(0, separator).trim()]: line.slice(separator + 1)
          };
        }, {}),
        type: header.replace('{', '').trim()
      };
    });

export {
  acknowledgeServiceProblem,
  addPersistentServiceComment,
  buildCheckProcessAgeCommand,
  buildEngineInstanceQuery,
  buildEngineSettingsQuery,
  buildHostStatusQuery,
  buildInstallTestCheckCommand,
  buildLogLineCountCommand,
  buildLogLinesAfterCommand,
  buildServiceCommentQuery,
  buildServiceStatusQuery,
  buildSetTestCheckBehaviourCommand,
  buildWriteExternalCommand,
  engineConfigurationDirectory,
  exitCodes,
  hostCheckCommandName,
  parseCfgObjects,
  parseRetentionBlocks,
  pollerName,
  processServiceCheckResult,
  scheduleFixedServiceDowntime,
  testCheckScript,
  weekDays
};
