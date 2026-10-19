import type { ClapiAction } from '../helpers/CentreonApi';

/**
 * A legacy configuration object whose listing offers the same edit,
 * duplicate and delete flow (`LegacyListingPage`).
 */
export interface LegacyObject {
  /** CLAPI object type used to seed and remove it. */
  clapi: string;
  /** CLAPI `ADD` values for an object called `name`. */
  addValues: (name: string) => string;
  /** Text field of the form changed by the test. */
  editField: string;
  /** Human-readable label of the object type. */
  label: string;
  /** CLAPI actions linking it to the objects its form requires. */
  links?: (name: string) => Array<ClapiAction>;
  /** Shared objects it relies on, created once and kept. */
  prerequisites?: Array<ClapiAction>;
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

/** CLAPI actions creating an object called `name` with its required fields. */
export const legacyObjectProvisioning = (
  object: LegacyObject,
  name: string
): Array<ClapiAction> => [
  { action: 'ADD', object: object.clapi, values: object.addValues(name) },
  ...(object.requiredParams ?? []).map((param) => ({
    action: 'SETPARAM',
    object: object.clapi,
    values: `${name};${param}`
  })),
  ...(object.links?.(name) ?? [])
];

/** Best-effort removal of an object and of its duplicate. */
export const legacyObjectCleanup = (
  object: LegacyObject,
  name: string
): Array<ClapiAction> =>
  [name, `${name}_1`].map((values) => ({
    action: 'DEL',
    object: object.clapi,
    values
  }));
