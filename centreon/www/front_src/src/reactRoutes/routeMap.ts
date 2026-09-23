const routeMap = {
  about: '/administration/about',
  additionalConnectorConfiguration:
    '/configuration/additional-connector-configurations',
  agentConfigurations: '/configuration/pollers/agent-configurations',
  authentication: '/administration/authentication',
  authenticationDenied: '/authentication-denied',
  authTokens: '/administration/authentication-token',
  cloudNotificationConfiguration: '/configuration/notifications',
  commands: '/configuration/commands',
  dashboard: '/home/dashboards/library/:dashboardId',
  dashboards: '/home/dashboards/library',
  extensionsManagerPage: '/administration/extensions/manager',
  hostGroups: '/configuration/hosts/groups',
  install: '/install/install.php',
  login: '/login',
  logout: '/logout',
  notAllowedPage: '/not-allowed',
  pollerList: '/main.php?p=60901',
  pollerWizard: '/poller-wizard/1',
  publicPages: '/public/*',
  resetPassword: '/reset-password',
  resourceAccessManagement: '/administration/resource-access/rules',
  resources: '/monitoring/resources',
  upgrade: '/install/upgrade.php',
  vaultConfiguration: '/administration/parameters/vault'
};

export default routeMap;
