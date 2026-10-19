import type { CentreonApi, ClapiAction } from '../helpers/CentreonApi';
import { queryCentreonDb } from '../helpers/docker';

/**
 * A legacy configuration object whose listing offers the same edit,
 * duplicate and delete flow (`LegacyListingPage`).
 */
export interface LegacyObject {
  /** CLAPI object type used to seed and remove it, or a key for `sql` ones. */
  clapi: string;
  /** CLAPI `ADD` values for an object called `name`. */
  addValues?: (name: string) => string;
  /** Statements seeding and removing the types CLAPI does not handle. */
  sql?: {
    insert: (name: string) => Array<string>;
    remove: (names: Array<string>) => string;
  };
  /** Text field of the form changed by the test. */
  editField: string;
  /** Human-readable label of the object type. */
  label: string;
  /** CLAPI actions linking it to the objects its form requires. */
  links?: (name: string) => Array<ClapiAction>;
  /** Shared objects it relies on, created once and kept. */
  prerequisites?: Array<ClapiAction>;
  /** Shared objects CLAPI does not handle, inserted once and kept. */
  sqlPrerequisites?: Array<string>;
  /** CLAPI `SETPARAM` values the form requires and `ADD` leaves empty. */
  requiredParams?: Array<string>;
  /** Topology page of the listing (`main.php?p=...`). */
  topologyPage: string;
}

// Host every host dependency of the stack can rely on.
const pollerHost = 'Centreon-Server';
// Access group the seeded ACL objects are linked to.
const aclGroup = 'pw-acl-group';
// Vendor of the SNMP traps seeded by the tests.
const trapVendor = 'pw-trap-vendor';
// Parents of the seeded dependencies.
const parents = {
  host: 'pw-dep-host',
  hostGroup: 'pw-dep-hg',
  meta: 'pw-dep-meta',
  service: 'pw-dep-svc',
  serviceGroup: 'pw-dep-sg'
};

const inList = (names: Array<string>): string =>
  names.map((name) => `'${name}'`).join(', ');

// Disabled: an enabled one also needs the service the form creates for it,
// without which the configuration export fails.
const insertMetaService = (name: string): string =>
  `INSERT INTO meta_service (meta_name, meta_display, check_period, max_check_attempts, normal_check_interval, retry_check_interval, calcul_type, meta_select_mode, regexp_str, metric, data_source_type, meta_activate) SELECT '${name}', '${name} output', 1, 3, 5, 1, 'AVE', '2', 'pw', 'pl', 0, '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM meta_service WHERE meta_name = '${name}')`;

/** A virtual metric on a service of the central server. */
export const insertVirtualMetric = (name: string, service: string): string =>
  `INSERT INTO virtual_metrics (index_id, vmetric_name, def_type, rpn_function, unit_name, hidden, comment, vmetric_activate) SELECT id, '${name}', '0', 'rta,2,*', 'ms', '0', '${name} comment', '1' FROM centreon_storage.index_data WHERE host_name = 'Centreon-Server' AND service_description = '${service}'`;

// Dependencies all need their failure criteria (see the host dependency).
const dependency = (
  label: string,
  type: string,
  parent: string,
  topologyPage: string,
  {
    child,
    ...prerequisites
  }: Pick<LegacyObject, 'prerequisites' | 'sqlPrerequisites'> & {
    child?: string;
  }
): LegacyObject => ({
  addValues: (name) => `${name};${name} description;${type};${parent}`,
  clapi: 'DEP',
  editField: 'dep_description',
  label,
  // Their forms require a dependent object, unlike the host ones.
  links: child
    ? (name) => [{ action: 'ADDCHILD', object: 'DEP', values: `${name};${child}` }]
    : undefined,
  requiredParams: [
    'execution_failure_criteria;n',
    'notification_failure_criteria;n'
  ],
  topologyPage,
  ...prerequisites
});

export const legacyObjects: Array<LegacyObject> = [
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'ACLGROUP',
    editField: 'acl_group_alias',
    label: 'ACL access group',
    topologyPage: '50203'
  },
  {
    addValues: (name) => `${name};${name} description`,
    clapi: 'ACLACTION',
    editField: 'acl_action_description',
    label: 'ACL actions access',
    links: (name) => [
      { action: 'ADDACTION', object: 'ACLGROUP', values: `${aclGroup};${name}` }
    ],
    prerequisites: [
      { action: 'ADD', object: 'ACLGROUP', values: `${aclGroup};${aclGroup}` }
    ],
    topologyPage: '50204'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'ACLMENU',
    editField: 'acl_topo_alias',
    label: 'ACL menu access',
    topologyPage: '50201'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'ACLRESOURCE',
    editField: 'acl_res_alias',
    label: 'ACL resources access',
    topologyPage: '50202'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'CG',
    editField: 'cg_alias',
    label: 'contact group',
    topologyPage: '60302'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'HC',
    editField: 'hc_alias',
    label: 'host category',
    topologyPage: '60104'
  },
  {
    addValues: (name) => `${name};${name} alias;;;;`,
    clapi: 'HTPL',
    editField: 'host_alias',
    label: 'host template',
    topologyPage: '60103'
  },
  {
    addValues: (name) => `${name};${name} description;HOST;${pollerHost}`,
    clapi: 'DEP',
    editField: 'dep_description',
    label: 'host dependency',
    // Without them the form crashes (explode() on null, see MIGRATION.md).
    requiredParams: [
      'execution_failure_criteria;n',
      'notification_failure_criteria;n'
    ],
    topologyPage: '60407'
  },
  dependency('host group dependency', 'HG', parents.hostGroup, '60408', {
    child: `${parents.hostGroup}-child`,
    prerequisites: [parents.hostGroup, `${parents.hostGroup}-child`].map(
      (group) => ({ action: 'ADD', object: 'HG', values: `${group};${group}` })
    )
  }),
  dependency(
    'service dependency',
    'SERVICE',
    `${parents.host},${parents.service}`,
    '60409',
    {
      prerequisites: [
        {
          action: 'ADD',
          object: 'HOST',
          values: `${parents.host};${parents.host};192.0.2.1;generic-host;Central;`
        },
        {
          action: 'ADD',
          object: 'SERVICE',
          values: `${parents.host};${parents.service};generic-service`
        },
        // The engine refuses a service without check command.
        {
          action: 'SETPARAM',
          object: 'SERVICE',
          values: `${parents.host};${parents.service};check_command;check_centreon_ping`
        }
      ]
    }
  ),
  dependency(
    'service group dependency',
    'SG',
    parents.serviceGroup,
    '60410',
    {
      child: `${parents.serviceGroup}-child`,
      prerequisites: [parents.serviceGroup, `${parents.serviceGroup}-child`].map(
        (group) => ({ action: 'ADD', object: 'SG', values: `${group};${group}` })
      )
    }
  ),
  dependency('meta service dependency', 'META', parents.meta, '60411', {
    child: `${parents.meta}-child`,
    sqlPrerequisites: [
      insertMetaService(parents.meta),
      insertMetaService(`${parents.meta}-child`)
    ]
  }),
  {
    clapi: 'ESC',
    editField: 'esc_alias',
    label: 'escalation',
    sql: {
      insert: (name) => [
        `INSERT INTO escalation (esc_name, esc_alias, first_notification, last_notification, notification_interval) VALUES ('${name}', '${name} alias', 1, 2, 1)`,
        // The form requires a contact group.
        `INSERT INTO escalation_contactgroup_relation (escalation_esc_id, contactgroup_cg_id) SELECT e.esc_id, cg.cg_id FROM escalation e, contactgroup cg WHERE e.esc_name = '${name}' AND cg.cg_name = 'Supervisors'`
      ],
      remove: (names) =>
        `DELETE FROM escalation WHERE esc_name IN (${inList(names)})`
    },
    topologyPage: '60401'
  },
  {
    clapi: 'META',
    editField: 'meta_display',
    label: 'meta service',
    sql: {
      insert: (name) => [insertMetaService(name)],
      remove: (names) =>
        `DELETE FROM meta_service WHERE meta_name IN (${inList(names)})`
    },
    topologyPage: '60204'
  },
  {
    clapi: 'VMETRIC',
    editField: 'comment',
    label: 'virtual metric',
    sql: {
      insert: (name) => [insertVirtualMetric(name, 'Ping')],
      remove: (names) =>
        `DELETE FROM virtual_metrics WHERE vmetric_name IN (${inList(names)})`
    },
    topologyPage: '20408'
  },
  {
    clapi: 'CONNECTOR',
    editField: 'connector_description',
    label: 'connector',
    sql: {
      insert: (name) => [
        `INSERT INTO connector (name, description, command_line, enabled, created, modified) VALUES ('${name}', '${name} description', '/usr/lib64/centreon-connector/centreon_connector_perl', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())`
      ],
      remove: (names) => `DELETE FROM connector WHERE name IN (${inList(names)})`
    },
    topologyPage: '60806'
  },
  {
    addValues: (name) => `${name};${name} description`,
    clapi: 'SC',
    editField: 'sc_description',
    label: 'service category',
    topologyPage: '60209'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'SG',
    editField: 'sg_alias',
    label: 'service group',
    topologyPage: '60203'
  },
  {
    addValues: (name) => `${name};${name} alias;generic-service`,
    clapi: 'STPL',
    editField: 'service_alias',
    label: 'service template',
    topologyPage: '60206'
  },
  {
    addValues: (name) => `${name};.1.3.6.1.4.1.50000.1`,
    clapi: 'TRAP',
    editField: 'traps_comments',
    label: 'SNMP trap',
    prerequisites: [
      { action: 'ADD', object: 'VENDOR', values: `${trapVendor};${trapVendor}` }
    ],
    requiredParams: [`vendor;${trapVendor}`, 'output;$*'],
    topologyPage: '61701'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'VENDOR',
    editField: 'alias',
    label: 'SNMP vendor',
    topologyPage: '61702'
  },
  {
    addValues: (name) => `${name};${name} alias`,
    clapi: 'TP',
    editField: 'tp_alias',
    label: 'time period',
    topologyPage: '60304'
  }
];

/** Create an object called `name` with the fields its form requires. */
export const seedLegacyObject = async (
  adminApi: CentreonApi,
  object: LegacyObject,
  name: string
): Promise<void> => {
  // Shared objects, kept between tests.
  await adminApi.provision(object.prerequisites ?? [], { tolerate: [409] });
  for (const statement of object.sqlPrerequisites ?? []) {
    queryCentreonDb(statement);
  }
  if (object.sql) {
    for (const statement of object.sql.insert(name)) {
      queryCentreonDb(statement);
    }

    return;
  }
  await adminApi.provision([
    { action: 'ADD', object: object.clapi, values: object.addValues?.(name) ?? name },
    ...(object.requiredParams ?? []).map((param) => ({
      action: 'SETPARAM',
      object: object.clapi,
      values: `${name};${param}`
    })),
    ...(object.links?.(name) ?? [])
  ]);
};

/** Best-effort removal of an object and of its duplicate. */
export const removeLegacyObject = async (
  adminApi: CentreonApi,
  object: LegacyObject,
  name: string
): Promise<void> => {
  const names = [name, `${name}_1`];
  if (object.sql) {
    queryCentreonDb(object.sql.remove(names));

    return;
  }
  await adminApi.cleanUp(
    names.map((each) => ({
      action: 'DEL',
      object: object.clapi,
      values: each
    }))
  );
};
