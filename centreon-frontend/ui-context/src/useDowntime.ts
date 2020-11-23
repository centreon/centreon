import * as React from 'react';
import { defaultDowntime } from './UserContext';
import { Downtime } from './types';

interface DowntimeState {
  downtime: Downtime;
  setDowntime: React.Dispatch<React.SetStateAction<Downtime>>;
}

const useDowntime = (): DowntimeState => {
  const [downtime, setDowntime] = React.useState<Downtime>(defaultDowntime);

  return { downtime, setDowntime };
};

export default useDowntime;