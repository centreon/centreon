import { User, ThemeMode } from './types';

const defaultUser: User = {
  alias: '',
  default_page: '/monitoring/resources',
  isExportButtonEnabled: false,
  locale: navigator.language,
  name: '',
  themeMode: ThemeMode.light,
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  use_deprecated_pages: false
};

const defaultResourceStorageOptimizationMode = false;

const defaultAcl = {
  actions: {
    host: {
      acknowledgement: false,
      check: false,
      comment: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false
    },
    service: {
      acknowledgement: false,
      check: false,
      comment: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false
    }
  }
};

const defaultDowntime = {
  duration: 3600,
  fixed: true,
  with_services: false
};

const defaultRefreshInterval = 15;

const defaultAcknowledgement = {
  force_active_checks: false,
  notify: true,
  persistent: false,
  sticky: false,
  with_services: true
};

export {
  defaultUser,
  defaultAcl,
  defaultDowntime,
  defaultRefreshInterval,
  defaultAcknowledgement,
  defaultResourceStorageOptimizationMode
};
