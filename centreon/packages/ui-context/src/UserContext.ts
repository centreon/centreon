import * as React from 'react';

import { UserContext } from './types';

const defaultUser = {
  alias: '',
  isExportButtonEnabled: false,
  locale: navigator.language,
  name: '',
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  use_deprecated_pages: false,
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
  duration: 7200,
  fixed: true,
  with_services: true,
};

const defaultRefreshInterval = 15;

const defaultCloudServices = undefined;

const defaultAcknowledgement = {
  force_active_checks: false,
  notify: true,
  persistent: false,
  sticky: false,
  with_services: true,
};

const defaultContext: UserContext = {
  ...defaultUser,
  acknowledgement: defaultAcknowledgement,
  acl: defaultAcl,
  cloudServices: undefined,
  downtime: defaultDowntime,
  refreshInterval: defaultRefreshInterval,
};

const Context = React.createContext<UserContext>(defaultContext);

const useUserContext = (): UserContext => React.useContext(Context);

export default Context;

export {
  useUserContext,
  defaultUser,
  defaultAcl,
  defaultDowntime,
  defaultRefreshInterval,
  defaultCloudServices,
  defaultAcknowledgement,
};
