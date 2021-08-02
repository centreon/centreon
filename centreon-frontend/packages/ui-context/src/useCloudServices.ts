import * as React from 'react';

import { CloudServices } from './types';

const useCloudServices = (): CloudServices => {
  const [areCloudServicesEnabled, setAreCloudServicesEnabled] =
    React.useState(false);

  return { areCloudServicesEnabled, setAreCloudServicesEnabled };
};

export default useCloudServices;
