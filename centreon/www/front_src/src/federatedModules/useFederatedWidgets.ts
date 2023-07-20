import { useCallback, useEffect } from 'react';

import { useAtom } from 'jotai';

import { getData, useRequest, useDeepCompare } from '@centreon/ui';

import usePlatformVersions from '../Main/usePlatformVersions';

import { federatedWidgetsAtom, federatedWidgetsPropertiesAtom } from './atoms';
import { FederatedModule, FederatedWidgetProperties } from './models';

export const getFederatedWidget = (moduleName: string): string => {
  return `./widgets/${moduleName}/static/moduleFederation.json`;
};

export const getFederatedWidgetProperties = (moduleName: string): string => {
  return `./widgets/${moduleName}/static/properties.json`;
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

  const getFederatedModulesConfigurations = useCallback((): void => {
    if (!widgets) {
      return;
    }

    Promise.all(
      widgets?.map((moduleName) =>
        sendRequest({ endpoint: getFederatedWidget(moduleName) })
      ) || []
    ).then(setFederatedWidgets);

    Promise.all(
      widgets?.map((moduleName) =>
        sendRequestProperties({
          endpoint: getFederatedWidgetProperties(moduleName)
        })
      ) || []
    ).then(setFederatedWidgetsProperties);
  }, [widgets]);

  useEffect(() => {
    getFederatedModulesConfigurations();
  }, useDeepCompare([widgets]));

  return {
    federatedWidgets,
    federatedWidgetsProperties,
    getFederatedModulesConfigurations
  };
};

export default useFederatedWidgets;
