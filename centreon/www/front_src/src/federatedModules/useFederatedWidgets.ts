import { useCallback, useEffect } from 'react';

import { useAtom } from 'jotai';

import { getData, useDeepCompare, useRequest } from '@centreon/ui';
import { federatedWidgetsAtom } from '@centreon/ui-context';

import { store } from '../Main/Provider';
import usePlatformVersions from '../Main/usePlatformVersions';

import { difference, pluck } from 'ramda';
import { internalWidgetComponents } from '../Dashboards/SingleInstancePage/Dashboard/Widgets/widgets';
import { federatedWidgetsPropertiesAtom } from './atoms';
import { FederatedModule, FederatedWidgetProperties } from './models';
import { loadScript } from './utils';

const getFederatedWidgetFolder = (moduleName: string): string =>
  `./widgets/${moduleName}/static`;

export const getFederatedWidget = (moduleName: string): string => {
  return `${getFederatedWidgetFolder(moduleName)}/moduleFederation.json`;
};

const getFederatedWidgetProperties = (moduleName: string): string => {
  return `${getFederatedWidgetFolder(moduleName)}/properties.json`;
};

interface UseFederatedModulesState {
  federatedWidgets: Array<FederatedModule> | null;
  federatedWidgetsProperties: Array<FederatedWidgetProperties> | null;
  getFederatedModulesConfigurations: () => void;
}

const useFederatedWidgets = (): UseFederatedModulesState => {
  const { sendRequest } = useRequest<FederatedModule>({
    request: getData
  });
  const { sendRequest: sendRequestProperties } =
    useRequest<FederatedWidgetProperties>({
      request: getData
    });
  const [federatedWidgets, setFederatedWidgets] = useAtom(federatedWidgetsAtom);
  const [federatedWidgetsProperties, setFederatedWidgetsProperties] = useAtom(
    federatedWidgetsPropertiesAtom
  );
  const { getWidgets } = usePlatformVersions();

  const widgets = getWidgets();

  const externalWidgets = difference(
    widgets || [],
    pluck('moduleName', internalWidgetComponents)
  );

  const getFederatedModulesConfigurations = useCallback((): void => {
    if (!widgets) {
      return;
    }

    const timestamp = `?t=${new Date().getTime()}`;

    Promise.all(
      externalWidgets.map((moduleName) =>
        sendRequest({
          endpoint: `${getFederatedWidget(moduleName)}${timestamp}`
        })
      ) || []
    ).then((federatedWidgetConfigs: Array<FederatedModule>): void => {
      setFederatedWidgets(
        federatedWidgetConfigs.concat(internalWidgetComponents)
      );

      federatedWidgetConfigs
        .filter(({ preloadScript }) => preloadScript)
        .forEach(({ preloadScript, moduleName }) => {
          loadScript({
            scriptPath: `${getFederatedWidgetFolder(moduleName)}/${preloadScript}`,
            store
          });
        });
    });

    Promise.all(
      externalWidgets?.map((moduleName) =>
        sendRequestProperties({
          endpoint: `${getFederatedWidgetProperties(moduleName)}${timestamp}`
        })
      ) || []
    ).then((properties) =>
      setFederatedWidgetsProperties((current) => current.concat(properties))
    );
  }, [externalWidgets]);

  useEffect(
    () => {
      getFederatedModulesConfigurations();
    },
    useDeepCompare([widgets])
  );

  return {
    federatedWidgets,
    federatedWidgetsProperties,
    getFederatedModulesConfigurations
  };
};

export default useFederatedWidgets;
