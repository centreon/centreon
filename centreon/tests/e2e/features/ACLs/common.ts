// Default access objects of the enforcement scenarios, named in their Gherkin
const defaultAclGroup = 'rbt-acl-group';
const defaultMenuAccess = 'rbt-acl-menus';
const defaultResourcesAccess = 'rbt-acl-resources';

const aclUserPassword = 'Centreon!2021RbtAcl';

const resourcesStatusMenu = ['Monitoring', 'Resources Status'];
const dashboardsViewerMenu = ['Home', 'Dashboards', 'Viewer'];

// Hosts and services an access group may see, as computed by centAcl
const buildAclTableQuery = (aclGroup: string): string =>
  `SELECT h.name AS host, s.description AS service
    FROM centreon_storage.centreon_acl a
    INNER JOIN centreon_storage.hosts h ON h.host_id = a.host_id
    LEFT JOIN centreon_storage.services s
      ON s.host_id = a.host_id AND s.service_id = a.service_id
    WHERE a.group_id = (
      SELECT acl_group_id FROM centreon.acl_groups WHERE acl_group_name = '${aclGroup}'
    )`;

const buildGroupActionsCountQuery = (aclGroup: string): string =>
  `SELECT COUNT(*) AS count
    FROM acl_group_actions_relations r
    INNER JOIN acl_groups g ON g.acl_group_id = r.acl_group_id
    WHERE g.acl_group_name = '${aclGroup}'`;

const buildResourcesAccessChangedQuery = (resourcesAccess: string): string =>
  `SELECT changed FROM acl_resources WHERE acl_res_name = '${resourcesAccess}'`;

const buildCentAclCronQuery = (): string =>
  `SELECT running, time_launch, UNIX_TIMESTAMP() AS now
    FROM cron_operation WHERE name = 'centAcl.php'`;

const buildHostIdQuery = (hostName: string): string =>
  `SELECT host_id FROM host WHERE host_name = '${hostName}' AND host_register = '1'`;

const buildHostAcknowledgedQuery = (hostName: string): string =>
  `SELECT acknowledged FROM hosts WHERE name = '${hostName}' AND enabled = 1`;

const buildMetricIdQuery = (hostName: string, metricName: string): string =>
  `SELECT m.metric_id
    FROM metrics m
    INNER JOIN index_data i ON i.id = m.index_id
    WHERE i.host_name = '${hostName}' AND m.metric_name = '${metricName}'
    ORDER BY m.metric_id
    LIMIT 1`;

const uniqueSorted = (values: Array<string | null>): Array<string> =>
  [
    ...new Set(values.filter((value): value is string => value !== null))
  ].sort();

export {
  aclUserPassword,
  buildAclTableQuery,
  buildCentAclCronQuery,
  buildGroupActionsCountQuery,
  buildHostAcknowledgedQuery,
  buildHostIdQuery,
  buildMetricIdQuery,
  buildResourcesAccessChangedQuery,
  dashboardsViewerMenu,
  defaultAclGroup,
  defaultMenuAccess,
  defaultResourcesAccess,
  resourcesStatusMenu,
  uniqueSorted
};
