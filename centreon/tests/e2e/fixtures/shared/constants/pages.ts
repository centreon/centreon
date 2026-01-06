export const PAGES = {
  configuration: {
    login: '/centreon/login',
    aclAccessGroupsLegacy: '/centreon/main.php?p=50203',
    aclActionsAccessLegacy: '/centreon/main.php?p=50204',
    aclMenusAccessLegacy: '/centreon/main.php?p=50201',
    aclResourcesAccessLegacy: '/centreon/main.php?p=50202',
    accountParametersLegacy: '/centreon/main.php?p=50104&o=c',
    centreonUiParametersLegacy: '/centreon/main.php?p=50110&o=general',
    databasesPlatformStatusLegacy: '/centreon/main.php?p=50503',
    authenticationTokens: '/centreon/administration/authentication-token',
    contactsUsersLegacy: '/centreon/main.php?p=60301',
    contactGroupsLegacy: '/centreon/main.php?p=60302',
    agentConfigurations: '/centreon/configuration/pollers/agent-configurations',
    additionalConfigurations: '/centreon/configuration/additional-connector-configurations',
    hostsTemplatesLegacy: '/centreon/main.php?p=60103',
    hostsLegacy: '/centreon/main.php?p=60101',
    authentication: '/centreon/administration/authentication',
    timePeriodsLegacy: '/centreon/main.php?p=60304',
    commandsChecksLegacy: '/centreon/main.php?p=60801&type=2',
    servicesTemplatesLegacy: '/centreon/main.php?p=60206',
    servicesByHostLegacy: '/centreon/main.php?p=60201',
    centreonUiLegacy: '/centreon/main.php?p=50110&o=general'
  },
  monitoring: {
    resourcesStatus: '/centreon/monitoring/resources'
  }
};
