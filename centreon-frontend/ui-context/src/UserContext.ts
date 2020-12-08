import * as React from 'react';

import { UserContext } from './types';

const defaultUser = {
  name: '',
  alias: '',
  locale: navigator.language,
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
};

const defaultAcl = {
  actions: {
    host: {
      check: false,
      acknowledgement: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false,
      comment: false,
    },
    service: {
      check: false,
      acknowledgement: false,
      disacknowledgement: false,
      downtime: false,
      submit_status: false,
      comment: false,
    },
  },
};

const defaultDowntime = {
  default_duration: 7200,
};

const defaultRefreshInterval = 15;

const defaultContext: UserContext = {
  ...defaultUser,
  acl: defaultAcl,
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
};
