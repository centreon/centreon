import * as React from 'react';
import { defaultRefreshInterval } from './UserContext';
import { Downtime } from './types';

interface RefreshIntervalState {
  refreshInterval: number;
  setRefreshInterval: React.Dispatch<React.SetStateAction<number>>;
}

const useRefreshInterval = (): RefreshIntervalState => {
  const [refreshInterval, setRefreshInterval] = React.useState<Downtime>(defaultRefreshInterval);

  return { refreshInterval, setRefreshInterval };
};

export default useRefreshInterval;