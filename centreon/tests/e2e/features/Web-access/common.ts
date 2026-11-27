interface ClapiAction {
  action: string;
  object: string;
  values: string;
}

type Persona =
  | 'super_admin'
  | 'user_administrator'
  | 'user_editor'
  | 'user_operator'
  | 'user_unprivileged';

interface PersonaProfile {
  actions: Array<string>;
  // Topology paths granted in read-write, children included
  menus: Array<Array<string>>;
  reachApi: boolean;
  reachApiRealtime: boolean;
  withResources: boolean;
}

const personaPassword = 'Centreon!2021RbtPersona';

// Profiles of the non-admin personas, none of them has the admin flag:
// - user_administrator: every menu, every action, every resource;
// - user_editor: the hosts and services configuration and Resources Status;
// - user_operator: the Monitoring menus with the acknowledgement, downtime,
//   comment and submit result actions;
// - user_unprivileged: a contact with no access group.
const personaProfiles: Record<
  Exclude<Persona, 'super_admin'>,
  PersonaProfile
> = {
  user_administrator: {
    actions: ['*'],
    menus: [['Home'], ['Monitoring'], ['Configuration'], ['Administration']],
    reachApi: true,
    reachApiRealtime: true,
    withResources: true
  },
  user_editor: {
    actions: [],
    menus: [
      ['Monitoring', 'Resources Status'],
      ['Configuration', 'Hosts'],
      ['Configuration', 'Services']
    ],
    reachApi: false,
    reachApiRealtime: false,
    withResources: true
  },
  user_operator: {
    actions: [
      'host_acknowledgement',
      'host_comment',
      'host_schedule_downtime',
      'host_submit_result',
      'service_acknowledgement',
      'service_comment',
      'service_schedule_downtime',
      'service_submit_result'
    ],
    menus: [['Monitoring']],
    reachApi: false,
    reachApiRealtime: true,
    withResources: true
  },
  user_unprivileged: {
    actions: [],
    menus: [],
    reachApi: false,
    reachApiRealtime: false,
    withResources: false
  }
};

const isPersona = (value: string): value is Persona =>
  value === 'super_admin' || value in personaProfiles;

const clapiAction = (
  action: string,
  object: string,
  values: string
): ClapiAction => ({ action, object, values });

// CLAPI actions creating the contact of a persona and its access group.
// The super admin is the "admin" account of the platform: nothing to create.
const buildPersonaClapiActions = (persona: Persona): Array<ClapiAction> => {
  if (persona === 'super_admin') {
    return [];
  }

  const profile = personaProfiles[persona];
  const group = `${persona}-group`;
  const menu = `${persona}-menus`;
  const actionAccess = `${persona}-actions`;
  const resources = `${persona}-resources`;

  const actions: Array<ClapiAction> = [
    clapiAction(
      'ADD',
      'CONTACT',
      `${persona};${persona};${persona}@localhost;${personaPassword};0;1;en_US;local`
    )
  ];

  if (profile.reachApi) {
    actions.push(clapiAction('SETPARAM', 'CONTACT', `${persona};reach_api;1`));
  }
  if (profile.reachApiRealtime) {
    actions.push(
      clapiAction('SETPARAM', 'CONTACT', `${persona};reach_api_rt;1`)
    );
  }

  if (
    profile.menus.length === 0 &&
    profile.actions.length === 0 &&
    !profile.withResources
  ) {
    return actions;
  }

  actions.push(
    clapiAction('ADD', 'ACLGROUP', `${group};${group}`),
    clapiAction('SETCONTACT', 'ACLGROUP', `${group};${persona}`)
  );

  if (profile.menus.length > 0) {
    actions.push(clapiAction('ADD', 'ACLMENU', `${menu};${menu}`));
    profile.menus.forEach((path) => {
      actions.push(
        clapiAction('GRANTRW', 'ACLMENU', `${menu};1;${path.join(';')}`)
      );
    });
    actions.push(clapiAction('ADDMENU', 'ACLGROUP', `${group};${menu}`));
  }

  if (profile.actions.length > 0) {
    actions.push(
      clapiAction('ADD', 'ACLACTION', `${actionAccess};${actionAccess}`),
      clapiAction(
        'GRANT',
        'ACLACTION',
        `${actionAccess};${profile.actions.join('|')}`
      ),
      clapiAction('ADDACTION', 'ACLGROUP', `${group};${actionAccess}`)
    );
  }

  if (profile.withResources) {
    actions.push(
      clapiAction('ADD', 'ACLRESOURCE', `${resources};${resources}`),
      clapiAction('GRANT_HOST', 'ACLRESOURCE', `${resources};*`),
      clapiAction('GRANT_HOSTGROUP', 'ACLRESOURCE', `${resources};*`),
      clapiAction('GRANT_SERVICEGROUP', 'ACLRESOURCE', `${resources};*`),
      clapiAction('ADDRESOURCE', 'ACLGROUP', `${group};${resources}`)
    );
  }

  return actions;
};

// Answers of popup.php to a user without the Configuration > Pollers page or
// without the poller in its access groups
const gorgoneRefusalMessages = [
  'You are not allowed to reach this page',
  'No access rights to this Poller'
];

const gorgoneConfigurationTitle = 'Gorgone configuration file';

// Some legacy API errors are sent as a JSON string
const parseApiBody = (body: unknown): unknown => {
  if (typeof body !== 'string') {
    return body;
  }

  try {
    return JSON.parse(body);
  } catch {
    return body;
  }
};

const nowInSeconds = (): number => Math.floor(Date.now() / 1000);

const buildCentralPollerQuery = (): string =>
  `SELECT id, name FROM nagios_server WHERE localhost = '1' AND ns_activate = '1' ORDER BY id LIMIT 1`;

const buildContactAdminQuery = (contactAlias: string): string =>
  `SELECT contact_admin FROM contact WHERE contact_alias = '${contactAlias}'`;

const buildRealtimeServiceIdsQuery = (
  hostName: string,
  serviceName: string
): string =>
  `SELECT s.host_id, s.service_id
    FROM services s
    INNER JOIN hosts h ON h.host_id = s.host_id
    WHERE h.name = '${hostName}' AND s.description = '${serviceName}' AND s.enabled = 1`;

const buildCommentsCountQuery = (text: string): string =>
  `SELECT COUNT(*) AS count FROM comments WHERE data LIKE '%${text}%'`;

// Files of the directories holding one of the values as plain text
const buildFilesContainingCommand = (
  directories: Array<string>,
  values: Array<string>
): string =>
  `grep -rlF ${values.map((value) => `-e '${value}'`).join(' ')} ${directories.join(' ')} 2>/dev/null || true`;

// Monitoring configuration generated and deployed by Centreon
const generatedConfigurationDirectories = [
  '/etc/centreon-engine',
  '/var/cache/centreon/config'
];

// Markers of the objects and the comment a crafted result would create
const injectedCommandName = 'rbt-injected-cmd';
const injectedServiceName = 'rbt-injected-svc';
const injectedComment = 'rbt-injected-comment';

// A service name closing the current definition and adding a command and a service
const buildServiceNameWithDefinitions = (
  hostName: string,
  serviceName: string
): string =>
  [
    `${serviceName}`,
    '}',
    'define command {',
    `  command_name ${injectedCommandName}`,
    '  command_line /bin/true',
    '}',
    'define service {',
    `  service_description ${injectedServiceName}`,
    `  host_name ${hostName}`,
    `  check_command ${injectedCommandName}`,
    '}'
  ].join('\n');

// Performance data followed by a host comment command, in the format of the
// command files read by Gorgone and in the format of the Engine command pipe
const buildPerfdataWithCommand = ({
  hostName,
  pollerId,
  timestamp
}: {
  hostName: string;
  pollerId: number;
  timestamp: number;
}): string => {
  const command = `ADD_HOST_COMMENT;${hostName};1;rbt;${injectedComment}`;

  return [
    'rbt_value=1',
    `EXTERNALCMD:${pollerId}:[${timestamp}] ${command}`,
    `[${timestamp}] ${command}`
  ].join('\n');
};

const buildEngineLogCommandCountCommand = (hostName: string): string =>
  `grep -c 'EXTERNAL COMMAND: ADD_HOST_COMMENT;${hostName};1;rbt;${injectedComment}' /var/log/centreon-engine/centengine.log || true`;

// An APPLYCFG output line reporting an error, other than an "Errors: 0" count
const isExportErrorLine = (line: string): boolean =>
  /error/i.test(line) && !/errors?\s*:\s*0\b/i.test(line);

export type { ClapiAction, Persona };
export {
  buildCentralPollerQuery,
  buildCommentsCountQuery,
  buildContactAdminQuery,
  buildEngineLogCommandCountCommand,
  buildFilesContainingCommand,
  buildPerfdataWithCommand,
  buildPersonaClapiActions,
  buildRealtimeServiceIdsQuery,
  buildServiceNameWithDefinitions,
  generatedConfigurationDirectories,
  gorgoneConfigurationTitle,
  gorgoneRefusalMessages,
  injectedCommandName,
  injectedComment,
  injectedServiceName,
  isExportErrorLine,
  isPersona,
  nowInSeconds,
  parseApiBody,
  personaPassword
};
