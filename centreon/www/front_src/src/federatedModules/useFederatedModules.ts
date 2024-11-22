import { useCallback, useEffect } from 'react';

import { useAtom } from 'jotai';

import { getData, useDeepCompare, useRequest } from '@centreon/ui';
import { federatedModulesAtom } from '@centreon/ui-context';

import { store } from '../Main/Provider';
import usePlatformVersions from '../Main/usePlatformVersions';

import { FederatedModule } from './models';
import { loadScript } from './utils';

export const getFederatedModuleFolder = (moduleName: string): string =>
  `./modules/${moduleName}/static`;

export const getFederatedModuleFederationFile = (moduleName: string): string =>
  `${getFederatedModuleFolder(moduleName)}/moduleFederation.json`;

interface UseFederatedModulesState {
  federatedModules: Array<FederatedModule> | null;
  getFederatedModulesConfigurations: () => void;
}

const useFederatedModules = (): UseFederatedModulesState => {
  const { sendRequest } = useRequest<FederatedModule>({
    request: getData
  });
  const [federatedModules, setFederatedModules] = useAtom(federatedModulesAtom);
  const { getModules } = usePlatformVersions();

  const modules = getModules();

  const getFederatedModulesConfigurations = useCallback((): void => {
    if (!modules) {
      return;
    }

    const timestamp = `?t=${new Date().getTime()}`;

    Promise.all(
      modules?.map((moduleName) =>
        sendRequest({
          endpoint: `${getFederatedModuleFederationFile(moduleName)}${timestamp}`
        })
      ) || []
    ).then((federatedModuleConfigs: Array<FederatedModule>): void => {
      setFederatedModules(federatedModuleConfigs);

      federatedModuleConfigs
        .filter(({ preloadScript }) => preloadScript)
        .forEach(({ preloadScript, moduleName }) => {
          loadScript({
            scriptPath: `${getFederatedModuleFolder(moduleName)}/${preloadScript}`,
            store
          });
        });
    });
  }, [modules]);

  useEffect(
    () => {
      getFederatedModulesConfigurations();
    },
    useDeepCompare([modules])
  );

  return {
    federatedModules,
    getFederatedModulesConfigurations
  };
};

export default useFederatedModules;
