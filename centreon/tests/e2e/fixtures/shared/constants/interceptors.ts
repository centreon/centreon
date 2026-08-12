export const INTERCEPTORS = {
  ajax: {
    host_categories_toggle: '**/ajaxHostCategoriesToggle.php',
    host_listing: '**/ajaxHostListing.php*',
    host_template_listing: '**/ajaxHostTemplateListing.php*',
    host_toggle: '**/ajaxHostToggle.php'
  },
  api: {
    access_groups: '/centreon/api/latest/configuration/access-groups',
    administration_tokens: '/centreon/api/latest/administration/tokens',
    agent_configurations:
      '/centreon/api/latest/configuration/agent-configurations',
    authentication_configuration:
      '/centreon/api/latest/authentication/providers/configurations',
    authentication_provider:
      '/centreon/api/latest/administration/authentication/providers',
    centreon_configuration_service:
      '/centreon/api/internal.php?object=centreon_configuration_service',
    centreon_keepalive: '/centreon/api/internal.php?object=centreon_keepalive',
    centreon_metric: '/centreon/api/internal.php?object=centreon_metric',
    centreon_topcounter:
      '/centreon/api/internal.php?object=centreon_topcounter',
    commands_configuration: '/centreon/api/latest/configuration/commands',
    connector_configurations:
      '/centreon/api/latest/configuration/additional-connector-configurations',
    connectors_configuration: '/centreon/api/latest/configuration/connectors',
    contacts_groups: '/centreon/api/latest/configuration/contacts/groups',
    contacts_templates: '/centreon/api/latest/configuration/contacts/templates',
    dashboard_configuration: '/centreon/api/latest/configuration/dashboards',
    events_view_users: '/centreon/api/latest/users/filters/events-view',
    generate_reload_pollers:
      '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload',
    global_macros_configuration:
      '/centreon/api/latest/configuration/global-macros',
    host_services: '/centreon/api/latest/monitoring/hosts/*/services',
    hosts_configuration: '/centreon/api/latest/configuration/hosts',
    hosts_status: '/centreon/api/latest/monitoring/hosts/status',
    icons_configuration: '/centreon/api/latest/configuration/icons',
    local_authentication:
      '/centreon/api/latest/authentication/providers/configurations/local',
    monitor_event_view: '/monitor/api/latest/users/filters/events-view',
    monitor_navigation_list:
      '/monitor/api/internal.php?object=centreon_topology&action=navigationList',
    monitor_resources: '/centreon/api/latest/monitoring/resources',
    monitor_resources_details:
      '/monitor/api/latest/monitoring/resources/hosts/*/services/*',
    monitoring_servers: '/centreon/api/latest/configuration/monitoring-servers',
    navigation_list:
      '/centreon/api/internal.php?object=centreon_topology&action=navigationList',
    notifications_configuration:
      '/centreon/api/latest/configuration/notifications',
    plugins_configuration: '/centreon/api/latest/configuration/plugins',
    realtime_monitoring_servers: '/centreon/api/latest/monitoring/servers',
    service_status: '/centreon/api/latest/monitoring/services/status',
    standard_macros_configuration:
      '/centreon/api/latest/configuration/standard-macros',
    timeperiods_configuration: '/centreon/api/latest/configuration/timeperiods',
    users_configuration: '/centreon/api/latest/configuration/users',
    users_parameters:
      '/centreon/api/latest/configuration/users/current/parameters'
  },
  pages: {
    centreon_administration_aclgroup:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup',
    centreon_configuration_command:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_command',
    centreon_configuration_contact:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_contact',
    centreon_configuration_contactgroup:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_contactgroup',
    centreon_configuration_host:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_host',
    centreon_configuration_meta:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_meta',
    centreon_configuration_timeperiod:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod',
    centreon_configuration_timezone:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timezone',
    centreon_configuration_trap:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_trap',
    centreon_performance_service:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_performance_service',
    centreon_proxy:
      '/centreon/include/common/webServices/rest/internal.php?object=centreon_proxy',
    customViews_action: '/centreon/include/home/customViews/action.php',
    customViews_views: '/centreon/include/home/customViews/views.php',
    generation_cache: '/centreon/install/steps/process/generationCache.php',
    ldap_search:
      '/centreon/include/configuration/configObject/contact/ldapsearch.php',
    monitor_time_zone: '/monitor/include/common/userTimezone.php',
    step1_upgrade: '/centreon/install/step_upgrade/step1.php',
    step2_upgrade: '/centreon/install/step_upgrade/step2.php',
    step3_upgrade: '/centreon/install/step_upgrade/step3.php',
    step4_upgrade: '/centreon/install/step_upgrade/step4.php',
    step5_upgrade: '/centreon/install/step_upgrade/step5.php',
    time_period_object: '/centreon/main.php?p=508&object_type=timeperiod',
    time_zone: '/centreon/include/common/userTimezone.php'
  },
  static: {
    pendo:
      'https://guide.centreon.com/agent/static/b06b875d-4a10-4365-7edf-8efeaf53dfdd/pendo.js'
  }
};
