import { ListingVariant } from '@centreon/ui-context';

export const retrievedUser = {
  alias: 'Admin alias',
  default_page: '/monitoring/resources',
  is_export_button_enabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Admin',
  timezone: 'Europe/Paris',
  use_deprecated_pages: false,
  user_interface_density: ListingVariant.compact
};

export const retrievedParameters = {
  monitoring_default_acknowledgement_persistent: true,
  monitoring_default_acknowledgement_sticky: true,
  monitoring_default_downtime_duration: 3600,
  monitoring_default_refresh_interval: 15
};

export const retrievedActionsAcl = {
  host: {
    acknowledgement: true,
    check: true,
    downtime: true,
    forced_check: true
  },
  service: {
    acknowledgement: true,
    check: true,
    downtime: true,
    forced_check: true
  }
};

export const retrievedTranslations = {
  en: {
    hello: 'Hello'
  }
};

export const retrievedWeb = {
  modules: {},
  web: {
    fix: '0',
    major: '23',
    minor: '04',
    version: '23.04.1'
  },
  widgets: {}
};

export const retrievedLoginConfiguration = {
  custom_text: 'Custom text',
  icon_source: 'icon_source',
  image_source: 'image_source',
  platform_name: 'Platform name',
  text_position: null
};

export const retrievedProvidersConfiguration = [
  {
    authentication_uri:
      '/centreon/authentication/providers/configurations/local',
    id: 1,
    is_active: true,
    name: 'local'
  }
];
