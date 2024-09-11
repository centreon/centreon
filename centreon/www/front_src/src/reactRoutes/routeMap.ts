const routeMap = {
  about: '/administration/about',
  additionalConnectorConfiguration:
    '/configuration/additional-connector-configurations',
  apiTokens: '/administration/api-token',
  authentication: '/administration/authentication',
  authenticationDenied: '/authentication-denied',
  cloudNotificationConfiguration: '/configuration/notifications',
  dashboard: '/home/dashboards/library/:dashboardId',
  dashboards: '/home/dashboards/library',
  extensionsManagerPage: '/administration/extensions/manager',
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
  agentConfiguration: '/configuration/pollers/agents-configuration'
};

export default routeMap;
