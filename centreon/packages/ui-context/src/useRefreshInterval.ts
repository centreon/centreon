import * as React from 'react';

import { defaultRefreshInterval } from './UserContext';

interface RefreshIntervalState {
  refreshInterval: number;
  setRefreshInterval: React.Dispatch<React.SetStateAction<number>>;
}

const useRefreshInterval = (): RefreshIntervalState => {
  const [refreshInterval, setRefreshInterval] = React.useState<number>(
    defaultRefreshInterval,
  );

  return { refreshInterval, setRefreshInterval };
};

export default useRefreshInterval;
