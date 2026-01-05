export const PAGES = {
  configuration: {
    login: '/centreon/login',
    acl_access_groups_legacy: '/centreon/main.php?p=50203', 
    acl_actions_access_legacy: '/centreon/main.php?p=50204',
    acl_menus_access_legacy: '/centreon/main.php?p=50201',
    acl_resources_access_legacy: '/centreon/main.php?p=50202',
    account_parameters_legacy: '/centreon/main.php?p=50104&o=c',
    centreon_ui_parameters_legacy: '/centreon/main.php?p=50110&o=general',
    databases_platform_status_legacy: '/centreon/main.php?p=50503',
    authentication_tokens: '/centreon/administration/authentication-token',
    contacts_users_legacy: '/centreon/main.php?p=60301',
    contact_groups_legacy: '/centreon/main.php?p=60302',
    agent_configurations: '/centreon/configuration/pollers/agent-configurations',
    additional_configurations: '/centreon/configuration/additional-connector-configurations',
    hosts_templates_legacy: '/centreon/main.php?p=60103',
    hosts_legacy: '/centreon/main.php?p=60101',
    authentication: '/centreon/administration/authentication',
    time_periods_legacy: '/centreon/main.php?p=60304',
    commands_checks_legacy: '/centreon/main.php?p=60801&type=2',
    services_templates_legacy: '/centreon/main.php?p=60206',
    services_by_host_legacy: '/centreon/main.php?p=60201',
    centreon_ui_legacy: '/centreon/main.php?p=50110&o=general'
  },
  monitoring: {
    resources_status: "/centreon/monitoring/resources"
  }
};