import * as React from 'react';

import { PlatformModules } from './types';
import { defaultPlatformModules } from './UserContext';

interface PlatformModulesState {
  platformModules: PlatformModules;
  setPlatformModules: React.Dispatch<React.SetStateAction<PlatformModules>>;
}

const usePlatformModules = (): PlatformModulesState => {
  const [platformModules, setPlatformModules] = React.useState<PlatformModules>(
    defaultPlatformModules,
  );

  return { platformModules, setPlatformModules };
};

export default usePlatformModules;
