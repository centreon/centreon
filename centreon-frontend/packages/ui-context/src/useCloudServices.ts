import * as React from 'react';

import { defaultCloudServices } from './UserContext';
import { CloudServices } from './types';

interface CloudServicesState {
  areCloudServicesEnabled: CloudServices;
  setAreCloudServicesEnabled: React.Dispatch<
    React.SetStateAction<CloudServices>
  >;
}

const useCloudServices = (): CloudServicesState => {
  const [areCloudServicesEnabled, setAreCloudServicesEnabled] =
    React.useState<CloudServices>(defaultCloudServices);

  return { areCloudServicesEnabled, setAreCloudServicesEnabled };
};

export default useCloudServices;
