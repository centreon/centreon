import type { ClapiAction } from '../helpers/CentreonApi';

export interface DashboardSeed {
  description: string | null;
  name: string;
  panels: Array<unknown>;
  refresh: { interval: number | null; type: string };
}

const emptyDashboard = (
  name: string,
  description: string | null
): DashboardSeed => ({
  description,
  name,
  panels: [],
  refresh: { interval: null, type: 'global' }
});

/** Data used by the creation spec (mirrors fixtures/dashboards/creation). */
export const creationDashboards = {
  default: emptyDashboard('dashboard default', 'Dashboard with description'),
  fromCreator: emptyDashboard(
    'dashboard-from-dashboard-creator-user',
    'created by user-dashboard-creator'
  ),
  requiredOnly: emptyDashboard('dashboard with required props', null)
};

/**
 * Five dashboards seeded for the navigation / edition / deletion specs
 * (mirrors fixtures/dashboards/navigation/dashboards-single-page.json).
 * The specs reference these by name rather than by list position.
 */
export const seededDashboards: Array<DashboardSeed> = [
  emptyDashboard('dashboard-name-0', 'dashboard-description-0'),
  emptyDashboard('dashboard-name-1', 'dashboard-description-1'),
  emptyDashboard('dashboard-to-delete-name', 'dashboard-to-delete-description'),
  emptyDashboard('dashboard-to-edit-name', 'dashboard-to-edit-description'),
  emptyDashboard('dashboard-to-locate-name', 'dashboard-to-locate-description')
];

export const dashboardToEdit = seededDashboards[3];
export const dashboardToDelete = seededDashboards[2];
export const dashboardToLocate = seededDashboards[4];

/**
 * CLAPI actions provisioning the `user-dashboard-creator` contact and the ACL
 * group granting dashboard creation rights (mirrors
 * fixtures/resources/clapi/config-ACL/dashboard-configuration-creator.json).
 */
export const dashboardCreatorAclActions: Array<ClapiAction> = [
  {
    action: 'ADD',
    object: 'CONTACT',
    values:
      'user-dashboard-creator;user-dashboard-creator;user-dashboard-creator@centreon.test;Centreon@2023;0;1;en_US;local'
  },
  {
    action: 'SETPARAM',
    object: 'CONTACT',
    values: 'user-dashboard-creator;reach_api;1'
  },
  {
    action: 'ADD',
    object: 'ACLMENU',
    values: 'name-creator-ACLMENU;alias-creator-ACLMENU'
  },
  {
    action: 'GRANTRW',
    object: 'ACLMENU',
    values: 'name-creator-ACLMENU;0;Home;Dashboards;Creator;'
  },
  {
    action: 'ADD',
    object: 'ACLGROUP',
    values: 'name-creator-ACLGROUP;alias-creator-ACLGROUP'
  },
  {
    action: 'ADDMENU',
    object: 'ACLGROUP',
    values: 'name-creator-ACLGROUP;name-creator-ACLMENU'
  },
  {
    action: 'SETCONTACT',
    object: 'ACLGROUP',
    values: 'name-creator-ACLGROUP;user-dashboard-creator;'
  }
];
