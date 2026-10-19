import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  aclAtom,
  isOnPublicPageAtom,
  platformFeaturesAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import Widget from '.';
import {
  columnsForViewByHost,
  columnsForViewByService,
  metaServiceResources,
  options,
  resources,
  resourcesRegex,
  selectedColumnIds
} from './spec/testUtils';

const actions = {
  acknowledgement: true,
  check: true,
  comment: true,
  disacknowledgement: true,
  downtime: true,
  forced_check: true,
  submit_status: true
};

const platformVersions = {
  isCloudPlatform: false,
  modules: {
    'centreon-open-tickets': {
      fix: '0',
      major: '24',
      minor: '10',
      version: '23.10.0'
    }
  },
  web: { fix: '0', major: '23', minor: '10', version: '23.10.0' },
  widgets: {}
};

interface Props {
  // Loose on purpose: specs pass plain JSON panel data and options.
  data: object;
  isPublic?: boolean;
  options: object;
}

export const Default = ({
  data,
  isPublic = false,
  options: panelOptions
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(isOnPublicPageAtom, isPublic);
    created.set(aclAtom, {
      actions: { host: actions, service: actions }
    } as never);
    created.set(platformVersionsAtom, platformVersions as never);
    created.set(platformFeaturesAtom, {
      featureFlags: {},
      isCloudPlatform: false
    } as never);

    return created;
  }, [isPublic]);

  return (
    <MemoryRouter>
      <TestQueryProvider>
        <SnackbarProvider>
          <Provider store={store}>
            <div style={{ height: '100vh', width: '100%' }}>
              <Widget
                dashboardId={1}
                globalRefreshInterval={{ interval: 30, type: 'manual' }}
                hasDescription={false}
                id="1"
                isInViewport
                panelData={data as never}
                panelOptions={panelOptions as never}
                playlistHash="hash"
                refreshCount={0}
                widgetPrefixQuery="widget"
              />
            </div>
          </Provider>
        </SnackbarProvider>
      </TestQueryProvider>
    </MemoryRouter>
  );
};

// Test data the spec cannot import: its module chain loads @centreon/ui.
export const Data = (): null => {
  Object.assign(window, {
    resourcesTableData: {
      columnsForViewByHost,
      columnsForViewByService,
      metaServiceResources,
      options,
      resources,
      resourcesRegex,
      selectedColumnIds
    }
  });

  return null;
};
