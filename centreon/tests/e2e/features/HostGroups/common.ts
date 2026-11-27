// Number of reachable hosts the bug of MON-207136 was limited to
const lowestReachableHostsCount = 10;

const buildHostGroupCountQuery = (hostGroupName: string): string =>
  `SELECT COUNT(*) AS count FROM hostgroup WHERE hg_name = '${hostGroupName}'`;

const buildContactIdQuery = (contactName: string): string =>
  `SELECT contact_id FROM contact WHERE contact_alias = '${contactName}'`;

// Hosts an access group reaches, as computed by centAcl, lowest id first
const buildReachableHostsQuery = (aclGroup: string): string =>
  `SELECT DISTINCT a.host_id, h.name
    FROM centreon_storage.centreon_acl a
    INNER JOIN centreon_storage.hosts h ON h.host_id = a.host_id
    WHERE a.service_id IS NULL
      AND a.group_id = (
        SELECT acl_group_id FROM centreon.acl_groups WHERE acl_group_name = '${aclGroup}'
      )
    ORDER BY a.host_id`;

// Every member row of a host group, duplicates included
const buildHostGroupMembersQuery = (hostGroupName: string): string =>
  `SELECT h.host_id, h.host_name
    FROM hostgroup_relation r
    INNER JOIN host h ON h.host_id = r.host_host_id
    INNER JOIN hostgroup hg ON hg.hg_id = r.hostgroup_hg_id
    WHERE hg.hg_name = '${hostGroupName}'
    ORDER BY h.host_id`;

// Change entries of a host group written by a contact, with their details
const buildHostGroupChangeLogsQuery = (
  hostGroupName: string,
  contactId: number
): string =>
  `SELECT a.action_log_id, m.field_name, m.field_value
    FROM centreon_storage.log_action a
    INNER JOIN centreon_storage.log_action_modification m
      ON m.action_log_id = a.action_log_id
    WHERE a.object_type = 'hostgroup'
      AND a.object_name = '${hostGroupName}'
      AND a.action_type = 'c'
      AND a.log_contact_id = ${contactId}`;

const duplicates = (values: Array<string>): Array<string> =>
  values.filter((value, index) => values.indexOf(value) !== index);

export {
  buildContactIdQuery,
  buildHostGroupChangeLogsQuery,
  buildHostGroupCountQuery,
  buildHostGroupMembersQuery,
  buildReachableHostsQuery,
  duplicates,
  lowestReachableHostsCount
};
