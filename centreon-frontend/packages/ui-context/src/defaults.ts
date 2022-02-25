const defaultUser = {
  alias: '',
  defaultPage: '/monitoring/resources',
  isExportButtonEnabled: false,
  locale: navigator.language,
  name: '',
  passwordRemainingTime: null,
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  useDeprecatedPages: false,
};

const defaultAcl = {
  actions: {
    host: {
      acknowledgement: false,
      check: false,
      comment: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false,
    },
    service: {
      acknowledgement: false,
      check: false,
      comment: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false,
    },
  },
};

const defaultDowntime = {
  default_duration: 7200,
  default_fixed: true,
  default_with_services: true,
};

const defaultRefreshInterval = 15;

const defaultAcknowledgement = {
  persistent: false,
  sticky: false,
};

export {
  defaultUser,
  defaultAcl,
  defaultDowntime,
  defaultRefreshInterval,
  defaultAcknowledgement,
};
